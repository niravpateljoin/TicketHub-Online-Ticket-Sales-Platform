# Setup Guide

## Prerequisites

| Requirement | Version |
|-------------|---------|
| Docker | 24+ |
| Docker Compose | 2.20+ |
| Node.js + npm | 20+ (frontend build only) |
| Make | any (optional but recommended) |

To run **without Docker** you additionally need:

- PHP 8.2+ with extensions: `pdo_pgsql`, `redis`, `amqp`, `intl`, `gd`
- Composer 2.x
- PostgreSQL 15+
- Redis 7+
- RabbitMQ 3.13+

---

## Installation (Docker — recommended)

### 1. Clone the repository

```bash
git clone <repository-url>
cd online-ticket-sales-platform
```

### 2. Copy environment file

```bash
cp .env .env.local
```

Edit `.env.local` if you need to override defaults (the Docker defaults work out of the box).

### 3. Start Docker containers

```bash
docker compose up -d --build
```

This starts: `php`, `nginx`, `database` (PostgreSQL 16), `redis`, `rabbitmq`, and all four Messenger worker containers.

Wait ~10 seconds for the database and RabbitMQ health checks to pass before the next step.

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

### 7. Load demo fixtures

```bash
make fixtures
# or:
docker compose exec php php bin/console doctrine:fixtures:load --no-interaction
```

### 8. Build frontend assets

```bash
npm install && npm run build
# or for live reloading during development:
npm run watch
```

### 9. Start Messenger workers (async jobs)

Workers start automatically with Docker Compose. To start them manually:

```bash
make workers
# or individually:
docker compose exec php php bin/console messenger:consume async --time-limit=3600
```

---

## Accessing the Application

| Service | URL | Credentials |
|---------|-----|-------------|
| Application | http://localhost | — |
| RabbitMQ Dashboard | http://localhost:15672 | guest / guest |
| PostgreSQL | localhost:5432 | tickets / tickets |
| Redis | localhost:6379 | — |

---

## Demo Accounts

| Role | Email | Password |
|------|-------|----------|
| Admin | `admin@example.com` | `password` |
| Organizer (approved) | `organizer@example.com` | `password` |
| User | `user@example.com` | `password` |

Each user account is seeded with **2000 credits**.

---

## Environment Variables Reference

| Variable | Default | Description |
|----------|---------|-------------|
| `APP_ENV` | `dev` | Symfony environment (`dev` / `prod`) |
| `APP_SECRET` | *(see .env)* | Symfony security secret — **change in production** |
| `DATABASE_URL` | `postgresql://tickets:tickets@database:5432/tickets` | PostgreSQL connection string |
| `MESSENGER_TRANSPORT_DSN` | `amqp://guest:guest@rabbitmq:5672/%2f/messages` | RabbitMQ AMQP connection (or `doctrine://default` for simpler setups) |
| `MAILER_DSN` | `smtp://mailer:1025` | Mailer transport — swap for real SMTP in production |
| `REDIS_URL` | `redis://redis:6379` | Redis connection |
| `JWT_PASSPHRASE` | `change-this-jwt-passphrase` | Passphrase for JWT RSA keypair — **change in production** |
| `JWT_TOKEN_TTL` | `3600` | JWT token lifetime in seconds |
| `CORS_ALLOW_ORIGIN` | `^https?://(localhost\|127\.0\.0\.1)(:[0-9]+)?$` | Allowed CORS origins |
| `LOCK_DSN` | `flock` | Symfony Lock store (`flock` for local, `redis://...` for multi-server) |

---

## Installation Without Docker

```bash
composer install
php bin/console lexik:jwt:generate-keypair --overwrite
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
php bin/console messenger:consume async &   # start background worker
symfony serve
# or: php -S localhost:8080 -t public/
```

Make sure `DATABASE_URL`, `MESSENGER_TRANSPORT_DSN`, and `MAILER_DSN` in `.env.local` point to your local services.

---

## Make Commands

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
