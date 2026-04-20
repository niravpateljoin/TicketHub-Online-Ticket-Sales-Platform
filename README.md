# TicketHub — Online Ticket Sales Platform

A multi-role ticket sales platform built with **Symfony 7 + React + PostgreSQL**, engineered for real-world concurrent booking scenarios — race conditions, overselling prevention, seat hold timeouts, and flash sale windows.

---

## Table of Contents

- [Screenshots](#screenshots)
- [Tech Stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Project Setup](#project-setup)
- [Running the Project](#running-the-project)
- [Demo Credentials](#demo-credentials)
- [Environment Variables](#environment-variables)
- [Available Make Commands](#available-make-commands)
- [User Roles](#user-roles)
- [Architecture Highlights](#architecture-highlights)

---

## Screenshots

### Home page

![Home Page 1](docs/Screenshots/home1.png)
![Home Page 2](docs/Screenshots/home2.png)
![Home Page 3](docs/Screenshots/home3.png)


### User Dashboard
![User Dashboard 1 ](docs/Screenshots/user-dashboard-1.png)
![User Dashboard 2 ](docs/Screenshots/user-dashboard-2.png)
![User Dashboard 3 ](docs/Screenshots/user-dashboard-3.png)


### event detail page
![Event Detail](docs/Screenshots/event-details.png)


### User — Cart & Checkout
![Cart page](docs/Screenshots/cart.png)
![checkout page](docs/Screenshots/checkout.png)


### User — confirm purchese and e-ticket
![confirm purchase](docs/Screenshots/confirm_purches.png)
![e-ticket](docs/Screenshots/e-ticket.png)


### Organizer — Dashboard and QR Scanning 
![Organizer Dashboard](docs/Screenshots/or-dashboard.png)
![QR Scanning](docs/Screenshots/qr-scanning.png)



### Organizer — Event Management  
![Event List](docs/Screenshots/event-list.png)
![Event create](docs/Screenshots/create-event.png)
![Event edit](docs/Screenshots/edit-event.png)



### Organizer — revenue stats and booking management
![Revenue Stats](docs/Screenshots/revenue-stats.png)
![Booking Management](docs/Screenshots/booking-management.png)


### Admin — dashboard
![Dashboard](docs/Screenshots/admin-dashboard.png)

### Admin — administration , organizer and user management
![administration](docs/Screenshots/admin-dashboard.png)
![organizer management](docs/Screenshots/Organizers.png)
![user management](docs/Screenshots/users.png)


### Admin — Booking management and category management
![Booking Management](docs/Screenshots/admin-booking-management.png)
![Category Management](docs/Screenshots/category-management.png)


### Admin — error logs management
![Error Logs Management](docs/Screenshots/error-logs-management.png)



### Emails
![Verification Email](docs/Screenshots/booking-confirm-email.png)
![Booking confirmation](docs/Screenshots/verification-email.png)
![organization approval](docs/Screenshots/organization-approve-email.png)
---

## Tech Stack

| Layer        | Technology                                        |
|--------------|---------------------------------------------------|
| Backend      | Symfony 7 (PHP 8.2+), API-only JSON responses     |
| Frontend     | React 18, Tailwind CSS, Webpack Encore            |
| Database     | PostgreSQL 16                                     |
| Cache        | Redis 7 (tag-aware pools, rate limiting, locks)   |
| Message Bus  | RabbitMQ 3.13 via Symfony Messenger (AMQP)        |
| Auth         | JWT (`lexik/jwt-authentication-bundle`)           |
| PDF / QR     | Dompdf + `endroid/qr-code`                        |
| QR Scanning  | `html5-qrcode` (browser camera, mobile-friendly)  |
| Container    | Docker + Docker Compose                           |

---

## Prerequisites

Make sure the following are installed before proceeding:

- [Docker](https://docs.docker.com/get-docker/) (v24+)
- [Docker Compose](https://docs.docker.com/compose/) (v2.20+)
- [Node.js](https://nodejs.org/) (v20+) and npm — for building frontend assets
- [Make](https://www.gnu.org/software/make/) — optional but recommended

---

## Project Setup

### 1. Clone the repository

```bash
git clone <repository-url>
cd online-ticket-sales-platform
```

### 2. Copy the environment file

```bash
cp .env .env.local
```

Open `.env.local` and adjust any values if needed (see [Environment Variables](#environment-variables)). The defaults work out of the box with Docker Compose.

### 3. Build and start Docker containers

```bash
docker compose up -d --build
```

This starts: `php`, `nginx`, `database` (PostgreSQL), `redis`, `rabbitmq`, and all four Messenger workers.

Wait a few seconds for the database and RabbitMQ to become healthy before continuing.

### 4. Install PHP dependencies

```bash
docker compose exec php composer install
```

### 5. Generate JWT keypair

```bash
make jwt
# or:
docker compose exec php php bin/console lexik:jwt:generate-keypair --overwrite
```

### 6. Run database migrations

```bash
make migrate
# or:
docker compose exec php php bin/console doctrine:migrations:migrate --no-interaction
```

### 7. Load fixtures (demo data)

```bash
make fixtures
# or:
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

This seeds the database with:
- 1 Admin account
- 6 approved organizer accounts + 1 pending organizer
- 10 user accounts (each starting with 3 000–4 800 credits)
- Sample events across multiple categories with ticket tiers

### 8. Install frontend dependencies and build assets

```bash
npm install
npm run build
# or to watch for changes during development:
npm run watch
```

---

## Running the Project

Once setup is complete, the application is available at:

| Service            | URL                                    |
|--------------------|----------------------------------------|
| Application        | http://localhost                       |
| RabbitMQ Dashboard | http://localhost:15672 (guest / guest) |
| Redis              | localhost:6379                         |
| PostgreSQL         | localhost:5432 (tickets / tickets)     |

### Start / stop containers

```bash
make up      # Start all services
make down    # Stop all services
```

### Messenger workers

The four Messenger workers start automatically with Docker Compose. To start them manually:

```bash
make workers
```

| Worker                | Queue          | Handles                                          |
|-----------------------|----------------|--------------------------------------------------|
| `worker_ticket`       | `ticket`       | E-ticket PDF + QR code generation                |
| `worker_notification` | `notification` | Booking confirmation & event cancellation emails |
| `worker_payment`      | `payment`      | Refund audit logs                                |
| `worker_reservation`  | `reservation`  | Periodic seat reservation expiry                 |

---

## Demo Credentials

| Role                | Email                      | Password       |
|---------------------|----------------------------|----------------|
| **Admin**           | `admin@gmail.com`          | `admin123`     |
| **Organizer** (×6)  | `or1@gmail.com` … `or6@gmail.com` | `or123` |
| **Pending Org.**    | `pending@platform.com`     | `organizer123` |
| **User** (×10)      | `user1@platform.com` … `user10@platform.com` | `user123` |

---

## Environment Variables

Key variables in `.env` / `.env.local`:

| Variable                  | Default                                              | Description                              |
|---------------------------|------------------------------------------------------|------------------------------------------|
| `APP_ENV`                 | `dev`                                                | Symfony environment (`dev` / `prod`)     |
| `APP_SECRET`              | *(change in production)*                             | Symfony security secret                  |
| `DATABASE_URL`            | `postgresql://tickets:tickets@database:5432/tickets` | PostgreSQL connection string             |
| `MESSENGER_TRANSPORT_DSN` | `amqp://guest:guest@rabbitmq:5672/%2f/messages`      | RabbitMQ connection                      |
| `REDIS_URL`               | `redis://redis:6379`                                 | Redis connection                         |
| `MAILER_DSN`              | `smtp://mailer:1025`                                 | Mailer transport (swap for real SMTP)    |
| `JWT_PASSPHRASE`          | `change-this-jwt-passphrase`                         | Passphrase for JWT keypair               |
| `JWT_TOKEN_TTL`           | `3600`                                               | JWT token lifetime in seconds            |
| `CORS_ALLOW_ORIGIN`       | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$`    | Allowed CORS origins                     |

> In production, set `APP_ENV=prod`, change `APP_SECRET` and `JWT_PASSPHRASE`, and update `MAILER_DSN` to a real SMTP provider.

---

## Available Make Commands

```bash
make up              # Start all Docker services
make down            # Stop all Docker services
make build           # Rebuild Docker images (no cache)

make migrate         # Run database migrations
make fixtures        # Load demo fixtures
make jwt             # Generate JWT keypair

make front           # Start Webpack Encore dev server
make front-build     # Build frontend assets for production

make workers         # Start all four Messenger worker containers
make queue-status    # Show RabbitMQ queue depths and consumer counts
make failed-messages # List messages in the dead-letter queue
make retry-failed    # Retry all failed messages

make redis-keys      # List all Redis keys
make redis-flush     # Flush all Redis data (FLUSHALL)
make cache-warm      # Pre-populate categories and events list cache

make cc              # Clear Symfony application cache
make test            # Run PHPUnit test suite
make stan            # Run PHPStan static analysis (level 6)
make lint            # Run PHP-CS-Fixer in dry-run mode
```

---

## User Roles

| Role          | Access                                                                                |
|---------------|---------------------------------------------------------------------------------------|
| **Admin**     | Approve/reject organizers, cancel any event, manage bookings/refunds, view error logs |
| **Organizer** | Create and manage events, ticket tiers, view revenue, scan attendee QR codes          |
| **User**      | Browse events, add tickets to cart, checkout with credits, download e-tickets, join waitlists |

---

## Architecture Highlights

### Concurrency Safeguards

Five layers of protection against race conditions:

1. **Optimistic Locking** — `TicketTier` carries a `version` column. On flush, Doctrine verifies no concurrent transaction bumped the version. Conflict → `OptimisticLockException` → 409 response.
2. **Soft-Lock Reservations** — `SeatReservation` records hold seats for 10 minutes. Available count seen by other users is `total_seats − sold − active_reservations`. Workers expire stale holds automatically.
3. **DB Unique Constraints** — `idempotency_key` on `Booking` prevents double-submit; `(event_id, seat_number)` prevents numbered-seat collisions.
4. **Pessimistic Write Lock** — checkout acquires `SELECT … FOR UPDATE` on the `User` row before reading the credit balance, preventing concurrent deductions.
5. **Flash Sale Window** — tier `sale_starts_at` / `sale_ends_at` enforced server-side on every purchase attempt.

### Redis Caching

Tag-aware cache pools with targeted invalidation:

| Pool                   | TTL    | Invalidated on                                      |
|------------------------|--------|-----------------------------------------------------|
| `events_list_pool`     | 5 min  | Event create / update / cancel / delete / checkout  |
| `event_detail_pool`    | 5 min  | Event update / cancel / checkout                    |
| `categories_pool`      | 60 min | Category create / update / delete                   |
| `admin_stats_pool`     | 2 min  | Key-based delete (no tags)                          |
| `organizer_stats_pool` | 2 min  | Organizer event mutations                           |

> `availableSeats` is **never** served from cache — always queried live from PostgreSQL after any cache hit to prevent stale seat counts.

### Async Messaging (RabbitMQ)

All post-checkout side effects run in the background via a topic exchange (`ticket_platform`):

```
checkout confirmed
  ├── ticket.*       → GenerateETicketMessage   → PDF + QR saved to var/tickets/
  └── notification.* → BookingConfirmedMessage  → confirmation email dispatched

event cancelled
  ├── payment.*      → RefundIssuedMessage      → per-booking refund + audit log
  └── notification.* → EventCancelledMessage    → per-user cancellation email

attendee checked in (QR scan)
  └── stored directly in DB (ETicket.checkedInAt) — no async step needed
```

Workers are configured with `restart: unless-stopped` in Docker Compose and reconnect automatically if RabbitMQ restarts.
