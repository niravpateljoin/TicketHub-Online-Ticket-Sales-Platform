# Architecture

## System Diagram

```
                        ┌─────────────────────────────────────────────────────┐
                        │                    Docker Compose                    │
                        │                                                      │
  Browser (React SPA)   │   Nginx (port 80)                                   │
       │                │       │                                              │
       │  HTTP/JSON      │       ▼                                              │
       └───────────────►│   PHP-FPM (Symfony 7)                               │
                        │       │              │                               │
                        │       ▼              ▼                               │
                        │  PostgreSQL 16    Redis 7                            │
                        │  (primary store)  (cache + rate-limit + locks)      │
                        │                                                      │
                        │       │                                              │
                        │       ▼                                              │
                        │  RabbitMQ 3.13  ──►  worker_ticket                  │
                        │  (AMQP exchange)  ──►  worker_notification           │
                        │                   ──►  worker_payment               │
                        │                   ──►  worker_reservation            │
                        └─────────────────────────────────────────────────────┘
```

### Request flow

1. **React SPA** sends JSON requests (JWT in `Authorization: Bearer` header).
2. **Nginx** proxies to PHP-FPM on the `php` container.
3. **Symfony controllers** authenticate via `lexik/jwt-authentication-bundle`, enforce role firewall, delegate to services.
4. Services write to **PostgreSQL** and publish messages to **RabbitMQ**.
5. **Background workers** consume messages independently — PDF generation, emails, refund audit logs.
6. **Redis** caches event listings and stats; also backs the Symfony Rate Limiter and Symfony Lock.

---

## Role Structure

| Role | Symfony Security Role | What they can do |
|------|----------------------|-----------------|
| Anonymous | — | Browse public event listings |
| User | `ROLE_USER` | Add to cart, checkout, download e-tickets, view own bookings |
| Organizer | `ROLE_ORGANIZER` | Create/manage events and tiers, view revenue (requires admin approval) |
| Admin | `ROLE_ADMIN` | Approve/reject organizers, cancel any event, view platform-wide stats |

### How authorization is enforced

- **Firewall** (`config/packages/security.yaml`): All `/api/` routes require a valid JWT. Anonymous browsing hits public event-listing endpoints outside the firewall.
- **`#[IsGranted]` attributes** on controllers: each endpoint declares its minimum role. Organizer-only endpoints additionally check `$organizer->isApproved()` inside the service layer and throw `AccessDeniedException` if not.
- **Voters** are not used — role hierarchy (`ROLE_ADMIN > ROLE_ORGANIZER > ROLE_USER`) is declared in `security.yaml` and enforced by Symfony's access decision manager.
- **Ownership checks**: edit/delete operations verify the authenticated user owns the resource (e.g., organizer may only edit their own events).

---

## Directory Structure (key paths)

```
src/
  Controller/         # Thin HTTP adapters — auth, rate-limit, HTTP mapping only
  Service/            # All business logic (CheckoutService, CartService, …)
    Cache/            # Tag-aware Redis cache invalidation helpers
    RateLimiter/      # Symfony RateLimiter wrappers
  Entity/             # Doctrine ORM entities
  Repository/         # Custom query methods
  Message/            # Async message DTOs (Notification/, Ticket/, Payment/)
  MessageHandler/     # RabbitMQ consumers for each message type
  EventListener/      # Kernel exception → JSON error response transformer
  Security/           # JWT user provider, login success/failure handlers
  Dto/                # Request/response data transfer objects
  Exception/          # Domain exceptions (InsufficientCreditsException, etc.)
```

---

## Database Design Rationale

### Why `SeatReservation` is a DB record (not a session)

A session-based hold would be invisible to other server processes and workers. A `SeatReservation` row is visible to every PHP-FPM worker and every Messenger worker; the available-seat query subtracts `active_reservations` directly in SQL so concurrent users never see inflated availability. The `worker_reservation` consumer periodically expires stale holds and releases those seats back to the pool.

### Why `idempotencyKey` is on `Booking`

The checkout endpoint is called with a client-generated UUID. If the network drops after the server commits but before the client receives the response, the client resends the same key. The server detects the existing `Booking` row and returns it immediately without charging again. A unique DB constraint on `booking.idempotency_key` also catches the race where two concurrent requests with the same key both pass the pre-check and race to `INSERT` — only one wins; the other gets `UniqueConstraintViolationException` and is redirected to the winning booking. Implementation: `src/Entity/Booking.php:37-38`, `src/Service/CheckoutService.php:60-73`.

### Why `#[ORM\Version]` is on `TicketTier` (not `Event`)

Inventory pressure is per-tier: 200 concurrent users fighting over "VIP — 50 seats" all contend on the same `TicketTier` row. Putting the version on `Event` would serialize every tier update under a single event lock, which is unnecessarily coarse and would cause phantom conflicts between independent tiers of the same event. Doctrine increments the version column on every `UPDATE` to `TicketTier` and verifies it at flush time. Implementation: `src/Entity/TicketTier.php:39-41`.

---

## Redis Caching

Tag-aware cache pools with targeted invalidation:

| Pool | TTL | Invalidated on |
|------|-----|----------------|
| `events_list_pool` | 5 min | Event create / update / cancel / checkout |
| `event_detail_pool` | 5 min | Event update / cancel / checkout |
| `categories_pool` | 60 min | Category create / update / delete |
| `admin_stats_pool` | 2 min | Key-based delete (no tags) |
| `organizer_stats_pool` | 2 min | Organizer event mutations |

`availableSeats` is **never** served from cache — it is always queried live from PostgreSQL to prevent stale counts reaching users.

---

## Async Messaging (RabbitMQ)

All post-checkout side effects run via a topic exchange (`ticket_platform`):

```
checkout confirmed
  ├── ticket.*       → GenerateETicketMessage   → PDF + QR → var/tickets/
  └── notification.* → BookingConfirmedMessage  → confirmation email

event cancelled
  ├── payment.*      → RefundIssuedMessage      → per-booking audit log
  └── notification.* → EventCancelledMessage    → per-user cancellation email
```

Workers use `restart: unless-stopped` in Docker Compose and reconnect automatically if RabbitMQ restarts.
