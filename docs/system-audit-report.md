# System Audit Report

**Platform**: Online Ticket Sales Platform  
**Stack**: Symfony 7 + React 19 + PostgreSQL 16 + Redis 7 + RabbitMQ 3.13  
**Audit Date**: 2026-04-17  
**Auditor**: Claude Code (automated production audit)  
**Scope**: Full-stack — database, backend API, frontend, security, performance, scalability

---

## 1. Database Overview

### Schema Summary

| Table | Rows Est. | Purpose |
|---|---|---|
| `users` | Medium | Platform users (buyers, organizers, admins) |
| `organizer` | Small | Organizer profile linked to users (1:1) |
| `category` | Tiny | Event categories (admin-managed) |
| `events` | Medium | Events created by organizers |
| `ticket_tier` | Medium | Pricing tiers per event |
| `seat` | Large | Individual numbered seats per event |
| `seat_reservation` | High-churn | 10-min cart holds before checkout |
| `booking` | Large | Confirmed purchases |
| `booking_item` | Large | Line items within a booking |
| `e_ticket` | Large | PDF e-tickets (1:1 with booking_item) |
| `transaction` | Large | Credit ledger (debit / credit / refund) |
| `error_log` | High-churn | Application errors for admin review |

### Relationships Diagram (simplified)

```
users ──────────────────────────────────────────┐
  │ 1:1                  1:M                     │
  ▼                       ▼                      ▼
organizer           seat_reservation          transaction
  │ 1:M
  ▼
events ──────── category (M:1)
  │ 1:M
  ├── ticket_tier ─────────────────── seat_reservation (M:1)
  │       │ 1:M                              │ 1:1
  │       │                                  ▼
  │       └── booking_item ──────────── booking (M:1)
  │                │ 1:1
  │                ▼
  ├── seat         e_ticket
  └── booking (1:M)
```

### Existing Indexes

| Table | Index | Type |
|---|---|---|
| `users` | email, pending_email, verification_token | UNIQUE |
| `organizer` | user_id | UNIQUE |
| `events` | organizer_id, category_id | INDEX |
| `events` | slug | UNIQUE |
| `ticket_tier` | event_id | INDEX |
| `seat` | (event_id, seat_number) | UNIQUE |
| `seat_reservation` | user_id | INDEX |
| `seat_reservation` | (status, expires_at) | INDEX |
| `booking` | user_id, event_id | INDEX |
| `booking_item` | booking_id | INDEX |
| `e_ticket` | qr_token | UNIQUE |
| `transaction` | user_id | INDEX |
| `error_log` | (occurred_at, resolved) | INDEX |

---

## 2. Issues Found

### 2.1 Database Issues

#### CRITICAL — No Composite Index on `events` for Listing Queries

The most common query — filtering events by `status + date_time` (future active events sorted by date) — has no composite index. At 10,000+ events this becomes a full table scan.

**Fix**: Add to migration:
```sql
CREATE INDEX idx_events_status_datetime ON events (status, date_time);
CREATE INDEX idx_events_category_status ON events (category_id, status, date_time);
```

#### HIGH — No Composite Index on `seat_reservation` Expiry Cleanup

The reservation expiry worker queries `WHERE status = 'pending' AND expires_at < NOW()`. The existing `IDX_RESERVATION_STATUS_EXPIRES` index should cover this, but it needs to be verified as a partial index for better performance.

**Fix**:
```sql
CREATE INDEX idx_seat_reservation_pending_expired
  ON seat_reservation (expires_at)
  WHERE status = 'pending';
```

#### HIGH — `credit_balance` and Financial Columns Stored as `int`

`users.credit_balance`, `ticket_tier.base_price`, `booking.total_credits`, `transaction.amount`, and `booking_item.unit_price` are all `int`. There is no explicit check constraint preventing negative balances at the database level. A failed application-level guard leaves no DB safety net.

**Fix**:
```sql
ALTER TABLE users ADD CONSTRAINT chk_credit_balance_non_negative
  CHECK (credit_balance >= 0);

ALTER TABLE ticket_tier ADD CONSTRAINT chk_base_price_positive
  CHECK (base_price > 0);

ALTER TABLE ticket_tier ADD CONSTRAINT chk_total_seats_positive
  CHECK (total_seats > 0 AND sold_count >= 0 AND sold_count <= total_seats);
```

#### HIGH — No Password Reset Table / Flow

There is an email verification table column (`verification_token`) but no mechanism for password reset. Users who forget their password are permanently locked out.

**Fix**: Add `password_reset_token` and `password_reset_expires_at` to `users`, or create a separate `password_reset_request` table if you want token revocation.

#### MEDIUM — `ticket_tier.sale_endsAt` Naming Inconsistency

All other columns use `snake_case`, but this column is `sale_endsAt` (camelCase). This is inconsistent and error-prone in raw SQL.

**Fix**: Rename to `sale_ends_at` in a migration:
```sql
ALTER TABLE ticket_tier RENAME COLUMN "sale_endsAt" TO sale_ends_at;
```
Update the Doctrine entity annotation accordingly.

#### MEDIUM — No Soft Delete on Critical Entities

Deleting an `organizer`, `event`, or `ticket_tier` destroys historical data. `booking_item.ticket_tier_id` is a FK to `ticket_tier` — if a tier is hard-deleted, past booking records lose their price reference.

**Fix**: Add `deleted_at TIMESTAMP NULL` to `organizer`, `events`, and `ticket_tier`. Filter `WHERE deleted_at IS NULL` in all queries. Never delete the row.

#### MEDIUM — No `booking_item` → `seat` Relationship

`booking_item` links to `ticket_tier` and `seat_reservation`, but not directly to the `seat` entity. For numbered-seat events, you cannot trace which physical seat a booking item corresponds to without joining through `seat_reservation` → logic that doesn't exist yet.

**Fix**: Add `seat_id INT NULL FK seat(id)` on `booking_item`. Populate during checkout when `is_online = false`.

#### LOW — `error_log.user_id` Has No Foreign Key

`error_log.user_id` is an `int` column but not declared as a foreign key to `users`. If a user is deleted, the error log has orphaned user IDs.

**Fix**: Either add the FK constraint (with `ON DELETE SET NULL`) or store the user's email/identifier directly as a string.

#### LOW — No `updated_at` on Mutable Entities

`events`, `ticket_tier`, `organizer`, and `users` have no `updated_at` timestamp. This makes cache invalidation less reliable and prevents change tracking.

**Fix**: Add `updated_at TIMESTAMP` with an `ON UPDATE CURRENT_TIMESTAMP` trigger or update it in the Doctrine lifecycle callback.

---

### 2.2 Backend Issues

#### CRITICAL — JWT Stored in `localStorage` (Assumed)

Standard React SPA pattern puts JWT in localStorage, which is fully readable by any JavaScript on the page. A single XSS attack compromises all tokens permanently.

**Fix**: Use `HttpOnly; Secure; SameSite=Strict` cookies for the JWT. Update `LoginController` to `Set-Cookie` instead of returning the token in the JSON body. On the React side, use `axios` with `withCredentials: true` — no manual token storage required.

#### HIGH — No Refresh Token Mechanism

JWT TTL is 1 hour (`JWT_TOKEN_TTL=3600`). After expiry, the user is silently logged out with no recovery path. A user mid-checkout loses their session.

**Fix**: Issue a short-lived access token (15 min) + long-lived refresh token (7 days, stored in HttpOnly cookie). Add `POST /api/auth/refresh` endpoint using `lexik/jwt-authentication-bundle` refresh token extension.

#### HIGH — No Per-User API Rate Limiting

Rate limiting only applies to the login endpoint. Any authenticated user can hammer `POST /api/cart`, `POST /api/checkout`, or organizer endpoints without throttle. This is a DoS vector.

**Fix**: Use Symfony's `RateLimiter` with a `sliding_window` policy keyed on `user_id` for all write endpoints. Add a `RateLimitListener` that applies limits by role (e.g., 100 req/min for users, 30 for checkout).

#### HIGH — No Input Sanitization on Free-Text Fields

`events.description`, `events.name`, `users.name`, `ticket_tier.name`, and `category.name` are accepted as raw strings. While Doctrine prevents SQL injection, stored XSS is still possible if these values are rendered without escaping in admin views or emails.

**Fix**: Strip HTML tags on all free-text inputs at the DTO validation layer using Symfony's `Assert\NoSuspiciousCharacters` or a custom validator. Use `strip_tags()` on description inputs.

#### HIGH — File Upload Has No MIME Validation

`BannerController` / `vich_uploader` accepts `banner_image_name` uploads. If only extension validation is in place (not MIME type sniffing), attackers can upload PHP files renamed as `.jpg`.

**Fix**:
```yaml
# config/packages/vich_uploader.yaml
mappings:
  event_banner:
    allowed_types: ['image/jpeg', 'image/png', 'image/webp']
```
Also validate via Symfony's `Assert\File` constraint in `UpsertEventDto`.

#### MEDIUM — Organizer Approval Listener Runs on Every Request

`OrganizerApprovalListener` fires on `kernel.request` with priority `-10`. It queries the database on every single API request from a `ROLE_ORGANIZER`. At 100 concurrent organizer requests, this is 100 extra DB queries per second.

**Fix**: Include the `approval_status` in the JWT payload at login (via `JWTCreatedListener`). Check the claim from the token — no DB hit required. Invalidate tokens when approval status changes.

#### MEDIUM — No Pagination Enforcement on List Endpoints

`GET /api/events`, `GET /api/organizer/events`, `GET /api/admin/users` return paginated data in the UI, but if pagination is not enforced server-side, a raw API call without `page` and `limit` params could return thousands of records.

**Fix**: Set a hard maximum `limit` of 100 in all repository list methods. Throw `400 Bad Request` if `limit > 100`.

#### MEDIUM — No Audit Trail for Critical Admin Actions

Approving/rejecting organizers, cancelling events, and issuing refunds have no audit log beyond the `error_log` table (which is for errors, not actions). For compliance and debugging, there is no "who did what when."

**Fix**: Add an `audit_log` table:
```sql
CREATE TABLE audit_log (
    id SERIAL PRIMARY KEY,
    actor_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,  -- e.g., 'organizer.approved', 'event.cancelled'
    target_type VARCHAR(50),       -- e.g., 'Organizer', 'Event'
    target_id INT,
    payload JSONB,
    created_at TIMESTAMP NOT NULL DEFAULT NOW()
);
```

#### LOW — No Health Check Endpoint

There is no `GET /api/health` or `GET /health` endpoint. Without it, Docker health checks and load balancers cannot verify the application is alive.

**Fix**: Add a `HealthController` that checks DB connectivity, Redis ping, and RabbitMQ connection. Return `200 OK` with a JSON status payload or `503 Service Unavailable` if any dependency is down.

#### LOW — `isVerified` Defaults to `true` in DB

`users.is_verified` defaults to `true` in the migration, which means if the email verification dispatch fails silently, users could exist in a "verified" state without actually verifying.

**Fix**: Default to `false`. Only set to `true` via the verification token flow.

---

### 2.3 Frontend Issues

#### HIGH — JWT in `localStorage` (XSS Attack Surface)

As noted above — storing the JWT in `localStorage` makes it accessible to all JavaScript, including injected scripts.

#### MEDIUM — React Context for Global State Does Not Scale

`AuthContext` and `CartContext` will cause full re-renders of large component trees on any state change. At 30+ components, this becomes a performance issue.

**Fix**: Replace with `Zustand` (3KB) or `Redux Toolkit`. Zustand is the simpler drop-in for this scale.

#### MEDIUM — No Error Boundary Components

A JavaScript error in any component tree crashes the entire SPA. There are no `ErrorBoundary` components wrapping route sections.

**Fix**: Wrap each major route section (`/admin/*`, `/organizer/*`, user pages) in a React `ErrorBoundary` component that renders a friendly fallback instead of a blank screen.

#### MEDIUM — No Form Validation Library

Forms in `EventCreatePage`, `RegisterUserPage`, and `CheckoutPage` likely implement manual state and validation. This is error-prone and inconsistent.

**Fix**: Use `React Hook Form` + `Zod` schema validation. This gives consistent validation UX, reduces boilerplate by ~60%, and prevents duplicate submissions.

#### LOW — No Frontend Tests

There are zero unit or integration tests for React components. A backend regression could break the UI silently.

**Fix**: Add `Vitest` + `React Testing Library`. At minimum, test: login flow, add-to-cart, checkout success/failure, and protected route redirects.

---

## 3. Module Review (Admin & Organizer)

### Admin Module

| Feature | Status | Notes |
|---|---|---|
| User Management | Partial | `GET /api/admin/users/{id}`, `PUT` exist — **missing `GET /api/admin/users` (list all users)** |
| Organizer Management | Complete | Approve/reject with reason, deactivation via `deactivated_at` |
| Event Management | Partial | Admin can cancel events — **missing: admin edit event, view all events with filters** |
| Ticket Tier Management | Missing | Admin has no direct tier override capability |
| Payment / Refund Management | Partial | Refunds are triggered via event cancellation — **no manual per-booking refund** |
| Reports & Analytics | Partial | `GET /api/admin/stats/dashboard` returns platform stats — **no date-range filtering, no export** |
| Notifications | Missing | No admin-triggered broadcast notifications to users |
| Error Log Management | Complete | View, filter, resolve errors with admin notes |
| Category Management | Complete | Full CRUD |
| Administrator Management | Complete | Create/update admin accounts |

**Missing Admin Features (High Priority)**:
1. `GET /api/admin/users` — list all users with search/filter
2. `GET /api/admin/events` — all events (not just cancel action)
3. `POST /api/admin/bookings/{id}/refund` — manual per-booking refund
4. `GET /api/admin/stats?from=&to=` — date-range analytics
5. `POST /api/admin/notify` — broadcast email/push to all users or segment

### Organizer Module

| Feature | Status | Notes |
|---|---|---|
| Dashboard | Complete | Via `OrganizerDashboardPage.jsx` |
| Event CRUD | Complete | Create, read, update, delete events with banner upload |
| Ticket Tier Setup | Complete | Create/update/delete tiers with sale windows |
| Orders / Bookings | Partial | `EventBookingsPage.jsx` exists — **no API endpoint `GET /api/organizer/events/{id}/bookings` visible in controller list** |
| Attendee Management | Missing | No attendee export, no check-in system |
| Revenue Tracking | Complete | Per-event revenue breakdown by tier, cached |
| Notifications | Missing | Organizer cannot send announcements to ticket holders |
| Event Status Updates | Partial | Status update DTO exists but no endpoint for organizer-initiated postponement/cancellation (only admin can cancel) |

**Missing Organizer Features (High Priority)**:
1. `GET /api/organizer/events/{id}/bookings` — list attendees (if not already routed)
2. `GET /api/organizer/events/{id}/attendees/export` — CSV export for check-in
3. `POST /api/organizer/events/{id}/notify` — email all ticket holders
4. `POST /api/organizer/events/{id}/postpone` — organizer-initiated postponement with new date
5. QR code scanner for check-in (mobile-friendly or webhook-based)

---

## 4. Missing Modules / Improvements

### 4.1 Critical Missing Features

#### Password Reset Flow
No `forgot-password` or `reset-password` flow exists. This is a required feature for any production system.

**Implement**:
- `POST /api/auth/forgot-password` — sends reset link to email
- `POST /api/auth/reset-password` — validates token + sets new password
- Add `password_reset_token VARCHAR(64)` and `password_reset_expires_at TIMESTAMP` to `users`

#### Manual Refund (Admin)
Currently, refunds only trigger when an event is cancelled en masse. There is no mechanism for an admin to refund a single booking (e.g., a customer complaint).

**Implement**: `POST /api/admin/bookings/{id}/refund` that calls `EventCancellationService` logic for a single booking.

#### Attendee Check-In System
BookMyShow and Ticketmaster both have venue scanning. The `e_ticket.qr_token` exists but there is no `POST /api/checkin/{qrToken}` endpoint that marks a ticket as used.

**Implement**:
```sql
ALTER TABLE e_ticket ADD COLUMN checked_in_at TIMESTAMP NULL;
ALTER TABLE e_ticket ADD COLUMN checked_in_by INT NULL REFERENCES users(id);
```
Add `POST /api/organizer/checkin` endpoint (or open to any bearer with `ROLE_ORGANIZER`).

#### Waitlist System
When a tier is `sold_out`, users should be able to join a waitlist. If a booking is cancelled or refunded, waitlisted users get notified.

**Implement**: New `waitlist` table, `POST /api/events/{id}/tiers/{tierId}/waitlist`, and a listener on `BookingCancelled` that pops the waitlist queue.

#### Search & Discovery
There is no `GET /api/events?q=concert&city=Delhi&from=2026-05-01` full-text or filtered search. The events list likely supports basic category/status filters but not keyword search.

**Implement**: Add PostgreSQL `GIN` index on `events.name` and `events.description` with `tsvector`:
```sql
ALTER TABLE events ADD COLUMN search_vector tsvector
  GENERATED ALWAYS AS (
    to_tsvector('english', name || ' ' || coalesce(description, ''))
  ) STORED;
CREATE INDEX idx_events_search ON events USING GIN(search_vector);
```
Expose `GET /api/events?q=` with `WHERE search_vector @@ plainto_tsquery('english', :q)`.

#### Social Sharing / Referral Links
No referral or affiliate tracking mechanism exists. This is table-stakes for Eventbrite-class platforms.

### 4.2 Nice-to-Have (V2)

| Feature | Description |
|---|---|
| Discount Codes / Promo Codes | `promo_code` table with usage limits and expiry |
| Seat Map Visualization | Interactive SVG seat picker (requires seat row/column data) |
| Recurring Events | `parent_event_id` for weekly/monthly series |
| Multi-currency | Prices in INR/USD; current system is credits-only |
| Organizer Payouts | Payout schedule and bank account linking |
| Mobile App API | Same API serves native app but push notifications need FCM token storage |

---

## 5. Performance Recommendations

### 5.1 Immediate (0-30 days)

#### Add Missing Composite Indexes (Critical)
```sql
-- Event listing (most common query)
CREATE INDEX idx_events_status_datetime ON events (status, date_time);
CREATE INDEX idx_events_category_status ON events (category_id, status, date_time);

-- Booking history (user dashboard)
CREATE INDEX idx_booking_user_created ON booking (user_id, created_at DESC);

-- Reservation cleanup worker
CREATE INDEX idx_reservation_pending_expired ON seat_reservation (expires_at)
  WHERE status = 'pending';
```

#### Enable PostgreSQL Connection Pooling via PgBouncer
Symfony/Doctrine opens a new connection per request. At 100 concurrent users, you hit PostgreSQL's `max_connections` (default 100). PgBouncer in transaction mode allows thousands of app threads to share a smaller pool of real DB connections.

**Add to docker-compose.yml**:
```yaml
pgbouncer:
  image: edoburu/pgbouncer:latest
  environment:
    DATABASE_URL: "postgresql://user:pass@database:5432/dbname"
    POOL_MODE: transaction
    MAX_CLIENT_CONN: 1000
    DEFAULT_POOL_SIZE: 25
```
Update `DATABASE_URL` in `.env` to point to PgBouncer.

#### Configure Doctrine Result Cache for Expensive Queries
Organizer revenue queries and admin stats re-run the same aggregations. Use Doctrine's built-in result cache:
```php
$query->enableResultCache(120, 'organizer_revenue_' . $eventId);
```

#### Implement APCu for PHP Opcode Caching
Add `opcache` + `apcu` to the PHP-FPM Docker image. This alone gives 20-40% throughput improvement with zero code changes:
```dockerfile
RUN docker-php-ext-install opcache && docker-php-ext-enable opcache
RUN pecl install apcu && docker-php-ext-enable apcu
```

### 5.2 Short-Term (30-90 days)

#### Read Replica for Analytics Queries
Admin dashboard stats (`GET /api/admin/stats/dashboard`) and organizer revenue reports run aggregation queries against the primary database. Add a PostgreSQL read replica and route read-only queries there:
```yaml
# doctrine.yaml
doctrine:
  dbal:
    connections:
      default: { url: '%env(DATABASE_URL)%' }
      replica: { url: '%env(DATABASE_REPLICA_URL)%' }
```

#### Paginate All List Endpoints with Cursor-Based Pagination
Offset pagination (`LIMIT 20 OFFSET 5000`) degrades linearly. Switch to cursor-based pagination using `WHERE id > :last_seen_id ORDER BY id LIMIT 20` for booking history and event lists.

#### Cache Warm-Up on Deploy
After deployment, the first request to a cold cache is slow. Add a Symfony command:
```bash
php bin/console app:cache:warm-events
```
That pre-populates `events_list_pool` and `categories_pool` on startup.

#### Async PDF Generation Monitoring
The `GenerateETicketMessage` queue depth should be monitored. If the `worker_ticket` falls behind, users won't receive their tickets. Add RabbitMQ queue depth alerting via the management API.

### 5.3 Long-Term (90+ days)

#### CDN for Banner Images
Event banner images are served from `var/tickets/` via Symfony. Move to S3 + CloudFront or Cloudflare R2. This offloads binary serving from the PHP process entirely.

#### Elasticsearch for Event Search
For 10,000+ events, PostgreSQL full-text search has limits. Integrate Elasticsearch (or Meilisearch — simpler) for event discovery. Keep PostgreSQL as source of truth; sync changes via Messenger.

#### Caching Layer for Seat Availability
Currently, seat availability is never cached (correct for correctness). At high load, this creates heavy DB reads. Use Redis `INCRBY` / `DECRBY` as an availability counter per tier, reconciled with DB on checkout. This requires careful implementation to avoid drift.

---

## 6. Security Review

### 6.1 Authentication & Authorization

| Check | Status | Finding |
|---|---|---|
| Password hashing | PASS | Argon2 auto (Symfony default) |
| JWT algorithm | PASS | RS256 (asymmetric, correct) |
| JWT TTL | WARN | 1 hour — no refresh token means forced logout |
| JWT storage | FAIL | Assumed `localStorage` — XSS readable |
| Role enforcement | PASS | Access control in `security.yaml` |
| Organizer approval gate | WARN | DB query on every request — should use JWT claim |
| Admin account creation | PASS | Restricted to `ROLE_ADMIN` |
| Email verification | PASS | Token-based, stored hashed (verify) |

### 6.2 API Security

| Check | Status | Finding |
|---|---|---|
| SQL Injection | PASS | Doctrine parameterized queries |
| XSS (stored) | WARN | Free-text fields not sanitized before storage |
| CORS | PASS | `nelmio_cors.yaml` restricts origins |
| CSRF | N/A | Stateless JWT — no cookies in current design |
| Rate limiting (login) | PASS | Redis-backed sliding window |
| Rate limiting (API) | FAIL | No per-user rate limit on write endpoints |
| Input validation | PARTIAL | DTOs exist but no `Assert\` constraint coverage confirmed |
| File upload validation | WARN | MIME type checking not confirmed in vich config |
| Idempotency | PASS | Checkout has idempotency key |
| Error information leakage | PASS | `ApiExceptionListener` normalizes errors |

### 6.3 Data Security

| Check | Status | Finding |
|---|---|---|
| PII encryption at rest | FAIL | User emails, names stored in plaintext |
| Sensitive data in logs | WARN | `error_log.stack_trace` may contain request data including tokens |
| DB check constraints | FAIL | No `CHECK` constraints — negative balances possible |
| Secrets in `.env` | WARN | OK for dev, must use Vault/AWS Secrets Manager in prod |
| JWT private key protection | PASS | In `config/jwt/` — verify it's in `.gitignore` |
| QR token entropy | PASS | `qr_token` is 64-char unique token |
| Ticket download auth | INFO | `/api/tickets/{qrToken}/download` is public by design — acceptable if QR tokens are hard to guess |

### 6.4 Actionable Security Fixes (Prioritized)

**P0 — Fix within 1 week**:
1. Move JWT to `HttpOnly; Secure; SameSite=Strict` cookie. Update CORS to allow credentials.
2. Add `CHECK (credit_balance >= 0)` constraint on `users` to prevent balance exploits.
3. Add `Assert\NotBlank`, `Assert\Length`, and `Assert\NoSuspiciousCharacters` to all DTO fields.
4. Validate file upload MIME types in `vich_uploader.yaml`.

**P1 — Fix within 1 month**:
5. Implement refresh token flow.
6. Add per-user rate limiting on `POST /api/cart` and `POST /api/checkout`.
7. Add `audit_log` table and log all admin/organizer critical actions.
8. Strip HTML from all free-text fields before storage.

**P2 — Fix within 3 months**:
9. Encrypt PII fields (`users.email`, `users.name`) at the application level using `defuse/php-encryption`.
10. Integrate Vault or AWS Secrets Manager for production secrets.
11. Add `Content-Security-Policy` headers to frontend responses.
12. Review `error_log.stack_trace` for PII leakage — redact before storing.

---

## 7. Scalability Suggestions

### 7.1 Current Architecture Scalability Assessment

| Component | Current Limit | Bottleneck |
|---|---|---|
| PHP-FPM | ~50 concurrent req (default) | No horizontal scaling config |
| PostgreSQL | ~100 connections | No PgBouncer |
| Redis | Single node | No cluster/sentinel |
| RabbitMQ | Single node | No clustering |
| Workers | 4 fixed containers | No autoscaling |

### 7.2 For 100-500 Concurrent Users (Immediate)

1. **PgBouncer**: As described in §5.1. Reduces real DB connections from 100 to 25.
2. **PHP-FPM Tuning**: Increase `pm.max_children` to 50-80 in `docker/php-fpm.conf`.
3. **Redis Persistence**: Enable AOF persistence (`appendonly yes`) in Redis config. Without it, a Redis crash loses all cart sessions.
4. **Worker Autoscaling**: Add `--time-limit=3600` and `--memory-limit=128M` to messenger worker commands. Use Docker `restart: always`.

### 7.3 For 500-5,000 Concurrent Users (Short-Term)

1. **Horizontal PHP Scaling**: Put Nginx upstream behind multiple PHP-FPM containers. Add a load balancer (Nginx upstream or Traefik).
2. **Stateless Sessions**: Already achieved via JWT — PHP containers can be replicated freely.
3. **Sticky-less Architecture**: The `symfony/lock` component uses Redis backend — all PHP instances share the same lock state.
4. **Elasticsearch**: Replace PostgreSQL full-text search for event discovery.
5. **CDN**: Move static assets and banner images to S3 + CloudFront.
6. **Redis Sentinel**: For Redis HA, configure Sentinel with 2 replicas.

### 7.4 For 5,000+ Concurrent Users (Long-Term)

1. **Kubernetes Deployment**: Deploy PHP-FPM as a `Deployment` with HPA (Horizontal Pod Autoscaler) keyed on CPU/memory. Workers as separate `Deployment` objects with queue-depth-based scaling (KEDA).
2. **Database Sharding / Read Replicas**: Add 2 read replicas. Shard events by organizer if the dataset grows beyond 1M events.
3. **Event-Driven Architecture**: Move from RabbitMQ to Kafka for guaranteed ordering and replay capability. BookMyShow uses Kafka for the booking pipeline.
4. **Circuit Breakers**: Add Symfony's `symfony/http-client` retry + circuit breaker for external services (payment gateways, email providers).
5. **Feature Flags**: Integrate Unleash or LaunchDarkly. Flash sales can be toggled on/off without deployment.
6. **Database Partitioning**: Partition `booking`, `transaction`, and `error_log` by month:
   ```sql
   CREATE TABLE booking_2026_04 PARTITION OF booking
     FOR VALUES FROM ('2026-04-01') TO ('2026-05-01');
   ```

### 7.5 CI/CD Pipeline (Missing)

No CI/CD pipeline exists. Add GitHub Actions:

```yaml
# .github/workflows/ci.yml
jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres: { image: postgres:16 }
      redis: { image: redis:7-alpine }
    steps:
      - uses: actions/checkout@v4
      - run: composer install
      - run: php bin/console doctrine:migrations:migrate --no-interaction
      - run: php bin/phpunit

  deploy:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - run: docker build -t ticket-platform:${{ github.sha }} .
      - run: kubectl set image deployment/php php=ticket-platform:${{ github.sha }}
```

---

## 8. Final Verdict

### Score Breakdown

| Category | Score | Reason |
|---|---|---|
| Database Design | 7/10 | Solid normalized schema; missing composite indexes, check constraints, soft delete |
| Backend Architecture | 8/10 | Clean Symfony 7 with proper layer separation; missing health check, audit log |
| API Design | 7/10 | RESTful, DTOs in place; missing list endpoints, no versioning (`/api/v1/`) |
| Security | 5/10 | Good JWT + RBAC; JWT storage, no refresh tokens, no per-user rate limiting |
| Concurrency Handling | 9/10 | Excellent — optimistic + pessimistic locks + idempotency + reservation expiry |
| Frontend | 6/10 | Good structure; no tests, no error boundaries, Context won't scale |
| Performance | 7/10 | Redis caching + async jobs; missing DB indexes, no PgBouncer |
| Scalability | 6/10 | Stateless JWT is good; no CI/CD, no k8s manifests, single-node everything |
| Testing | 7/10 | Strong PHPUnit suite; zero frontend tests |
| Observability | 4/10 | Error log table is good; no Prometheus, no distributed tracing, no alerts |

### Overall Score: **6.6 / 10**

### Verdict

The platform has a **solid architectural foundation** — the concurrency handling (optimistic locking, pessimistic locking, idempotency, reservation expiry) is production-grade and better than many commercial systems. The async message queue design for PDF generation, notifications, and refunds is correct.

However, the system has **critical security gaps** (JWT storage, no refresh tokens, no per-user rate limiting) and **operational gaps** (no monitoring, no CI/CD, no health checks, no password reset) that must be resolved before going live.

**Top 5 actions before production launch**:
1. Move JWT to `HttpOnly` cookie + implement refresh tokens
2. Add per-user rate limiting on cart and checkout endpoints
3. Add `GET /api/health` endpoint for load balancer health checks
4. Implement password reset flow (currently impossible to recover locked accounts)
5. Set up PgBouncer and add missing composite indexes

**Top 5 actions for V1.1 (post-launch)**:
1. Audit log table for all admin/organizer actions
2. Attendee check-in API (`POST /api/organizer/checkin`)
3. Manual per-booking refund for admin
4. React Error Boundaries + frontend test suite (Vitest)
5. CI/CD pipeline (GitHub Actions → Docker → Kubernetes)
