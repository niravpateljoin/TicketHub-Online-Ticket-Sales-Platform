# Concurrency Strategy

This document covers the five concurrency challenges in the platform, the mechanism chosen for each, the reasoning behind the choice, and the exact location in code where it is implemented.

---

## Challenge 1 — Seat Inventory Race Condition (Overselling)

### Problem

Two users simultaneously view a tier with 1 remaining seat. Both read `soldCount = 49` (totalSeats = 50), both decide a seat is available, both proceed to checkout, and both try to increment `soldCount` to 50. Without protection, both succeed and 51 seats are sold.

### Mechanism: Optimistic Locking (`#[ORM\Version]` + `LockMode::OPTIMISTIC`)

Doctrine adds an integer `version` column to `ticket_tier`. On every `UPDATE`, Doctrine appends `AND version = :expectedVersion` to the `WHERE` clause and increments the column. If another transaction already committed an update (bumping the version), the `WHERE` clause matches 0 rows and Doctrine throws `OptimisticLockException`.

### Why Optimistic (not Pessimistic)?

- **Read-heavy workload**: many users browse seat counts; a pessimistic `SELECT … FOR UPDATE` would block every concurrent read for the duration of the checkout transaction (potentially seconds), creating a bottleneck under flash-sale load.
- **Conflict rate is low**: the overwhelming majority of checkouts complete without collision. Optimistic locking adds zero overhead on the happy path — it is only a version comparison on `UPDATE`.
- **Pessimistic lock scope is too broad**: locking the `TicketTier` row for the entire checkout would also block unrelated reads like event detail pages that include seat counts.

### Where in code

| File | Line | What happens |
|------|------|--------------|
| `src/Entity/TicketTier.php` | 39–41 | `#[ORM\Version]` annotation declares the version column |
| `src/Service/CheckoutService.php` | 127 | `$this->em->lock($tier, LockMode::OPTIMISTIC, $tier->getVersion())` registers the expected version |
| `src/Service/CheckoutService.php` | 217 | `$this->em->flush()` triggers the version check — throws `OptimisticLockException` on mismatch |
| `src/Service/CheckoutService.php` | 253–257 | `catch (OptimisticLockException)` rolls back and re-throws → controller returns HTTP 409 |

---

## Challenge 2 — Seat Hold Expiry (Soft Lock)

### Problem

A user adds tickets to their cart (reserving seats for 10 minutes) and then abandons the browser tab. Those seats are held indefinitely unless expiry is enforced, starving other buyers.

A second race: a user's reservation expires at T=0, and at T=1ms they submit checkout while a background worker also tries to expire the reservation at T=1ms. Without a check, the checkout could succeed on an expired reservation.

### Mechanism: `SeatReservation` DB record + expiry re-check at checkout

Each `SeatReservation` row has `expires_at = NOW() + 10 minutes` and `status = pending|confirmed|expired`. Available-seat queries subtract `COUNT(active reservations)` directly in SQL. At checkout, the `status` and `expires_at` are re-checked inside the transaction (after acquiring locks) before any money or inventory changes.

### Why a DB record (not a session)?

A session hold is invisible to other PHP workers and Messenger workers. A DB record is a shared, durable, transactionally consistent source of truth visible to every process.

### Where in code

| File | Line | What happens |
|------|------|--------------|
| `src/Entity/SeatReservation.php` | 37, 48 | `expiresAt` set to `+10 minutes` on construction |
| `src/Entity/SeatReservation.php` | 118 | `isExpired()` helper — `expiresAt < now()` |
| `src/Service/CheckoutService.php` | 116–119 | Explicit re-check: `status !== PENDING || isExpired()` → `ReservationExpiredException` |
| `src/Service/CartService.php` | 119 | Same check in `getCheckoutContext()` (belt-and-suspenders) |

---

## Challenge 3 — Numbered Seat Collision

### Problem

Two users attempt to book the same numbered seat (e.g., Row B, Seat 12) at the same time. Both pass availability checks and try to insert a confirmed `SeatReservation` for the same `(event_id, seat_number)`.

### Mechanism: DB Unique Constraint on `(event_id, seat_number)`

A `UNIQUE` constraint in PostgreSQL is the lowest-level, most reliable guard. Regardless of application-layer logic, the database guarantees that only one row can exist per seat. The loser gets a `UniqueConstraintViolationException`.

### Why a DB constraint (not application-level check)?

Application-level "check then insert" is inherently racy under concurrent load. A database constraint is atomic — it is enforced by PostgreSQL's transaction isolation, not by the application.

### Where in code

| File | Line | What happens |
|------|------|--------------|
| `src/Entity/Seat.php` | (class-level `#[ORM\UniqueConstraint]`) | Declares `UNIQUE(event_id, seat_number)` |
| Relevant migration | `migrations/` | `CREATE UNIQUE INDEX` on the table |

---

## Challenge 4 — Double Checkout / Credit Race Condition

### Problem (two sub-problems)

**4a — Double-submit**: User clicks "Pay" twice rapidly. Two identical requests race to insert the same booking. Without a guard, both succeed and the user is charged twice.

**4b — Credit race**: Two concurrent checkout sessions for the same user (e.g., two browser tabs) both read `creditBalance = 500`, both confirm they have enough, both deduct 300 credits, and the balance goes negative (−100).

### Mechanism for 4a: Idempotency Key + DB Unique Constraint

The client sends a UUID (`X-Idempotency-Key` header) with every checkout. The server:
1. Pre-checks: if a `Booking` with this key already exists, return it immediately (fast path).
2. Sets `booking.idempotency_key` before flush — a `UNIQUE` constraint on the column catches the race where two requests both pass the pre-check and race to `INSERT`.

### Mechanism for 4b: Pessimistic Write Lock on `User`

Inside the transaction, before reading `creditBalance`:

```php
$lockedUser = $this->em->find(User::class, $user->getId(), LockMode::PESSIMISTIC_WRITE);
```

This issues `SELECT … FOR UPDATE` on the `user` row. Any concurrent checkout for the same user blocks here until the first transaction commits or rolls back. The credit balance is then read **after** the lock is acquired — never from a pre-lock snapshot.

### Why Pessimistic (not Optimistic) for credits?

Credits are financial. An optimistic retry loop on the user row is dangerous:
- It would allow the second request to re-read the post-deduction balance and try again — correct, but adds complexity.
- More critically, "check balance → deduct" must be atomic. Pessimistic lock makes this trivially safe: only one transaction at a time can be in the balance-check+deduction critical section.
- The `user` row is not a hot read target like `TicketTier`; serializing credit deductions per user is acceptable and expected.

### Where in code

| File | Line | What happens |
|------|------|--------------|
| `src/Entity/Booking.php` | 37–38 | `idempotencyKey` column with `unique: true` |
| `src/Service/CheckoutService.php` | 60–73 | Idempotency pre-check (fast path) |
| `src/Service/CheckoutService.php` | 84 | `LockMode::PESSIMISTIC_WRITE` on User row |
| `src/Service/CheckoutService.php` | 103–105 | Credit balance read *after* lock, not before |
| `src/Service/CheckoutService.php` | 160 | `setIdempotencyKey()` — DB constraint is the last defense |
| `src/Service/CheckoutService.php` | 259–275 | `catch (UniqueConstraintViolationException)` — redirect loser to winner's booking |

---

## Challenge 5 — Flash Sale Window Enforcement

### Problem

A tier has `sale_starts_at = 14:00` and `sale_ends_at = 15:00`. A user with the event page open attempts to add a ticket at 14:59:59, but the request arrives at the server at 15:00:01. Or a malicious user replays a valid cart request after the window has closed.

### Mechanism: Server-side timestamp check on every purchase attempt

The application never trusts client-side time. `sale_starts_at` and `sale_ends_at` are checked server-side on **both** `addToCart` and at checkout (inside the transaction). The check uses `new \DateTimeImmutable()` at the moment of evaluation — not a cached value.

### Why server-side (not client-side)?

Client clocks can be wrong or manipulated. Enforcing on the server inside the checkout transaction ensures even a replay attack on a valid reservation is blocked after the window closes.

### Where in code

| File | Line | What happens |
|------|------|--------------|
| `src/Service/CartService.php` | 219–239 | `assertTierCanBePurchased()` checks `saleStartsAt` and `saleEndsAt` against `new \DateTimeImmutable()` |
| `src/Service/CartService.php` | 212 | Called from `assertTierCanBeReserved()` → enforced at add-to-cart time |
| `src/Service/CartService.php` | 128 | Called again inside `getCheckoutContext()` → enforced at checkout time |

---

## Race Condition Reproduction Guide

The following steps reproduce each race condition in a local Docker environment for manual verification.

### Challenge 1 — Overselling (Optimistic Lock)

1. Create a tier with `total_seats = 1`.
2. Open two browser tabs as two different users, both add that tier to cart.
3. Submit checkout from both tabs within the same second (or use a tool like `k6` or two `curl` processes fired in parallel).
4. **Expected**: one checkout returns HTTP 200 (booking confirmed); the other returns HTTP 409 (`OptimisticLockException` caught → "Seat inventory conflict, please try again").

### Challenge 2 — Expired Reservation

1. Add a tier to cart.
2. Manually expire the reservation in the database:
   ```sql
   UPDATE seat_reservation SET expires_at = NOW() - INTERVAL '1 minute', status = 'expired' WHERE status = 'pending';
   ```
3. Submit checkout.
4. **Expected**: HTTP 409 with `ReservationExpiredException` message.

### Challenge 4a — Idempotency (Double-submit)

```bash
KEY=$(uuidgen)
curl -X POST http://localhost/api/checkout \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Idempotency-Key: $KEY" &
curl -X POST http://localhost/api/checkout \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Idempotency-Key: $KEY" &
wait
```

**Expected**: both return HTTP 200 with the same `bookingId`; credits are deducted only once.

### Challenge 4b — Credit Race

```bash
# Run two checkouts for the same user with independent idempotency keys but from the same account:
for i in 1 2; do
  curl -X POST http://localhost/api/checkout \
    -H "Authorization: Bearer $TOKEN" \
    -H "X-Idempotency-Key: $(uuidgen)" &
done
wait
```

**Expected**: the second checkout blocks until the first commits, then reads the updated balance. If balance after the first deduction is insufficient, the second returns HTTP 402 (Insufficient Credits) — never a negative balance.

### Challenge 5 — Flash Sale Window

1. Create a tier with `sale_ends_at = NOW() + 5 seconds`.
2. Add to cart (should succeed).
3. Wait 6 seconds.
4. Submit checkout.
5. **Expected**: HTTP 409 with "Ticket sales for '...' have ended."
