<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema: all tables for the Online Ticket Sales Platform.
 */
final class Version20260416000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create initial database schema: users, organizers, categories, events, ticket_tiers, seats, seat_reservations, bookings, booking_items, e_tickets, transactions';
    }

    public function up(Schema $schema): void
    {
        // Users
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
                id               SERIAL PRIMARY KEY,
                email            VARCHAR(180) NOT NULL,
                password         VARCHAR(255) NOT NULL,
                role             VARCHAR(30)  NOT NULL DEFAULT 'ROLE_USER',
                credit_balance   INT          NOT NULL DEFAULT 2000,
                timezone         VARCHAR(50)  NOT NULL DEFAULT 'UTC',
                created_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT uq_users_email UNIQUE (email)
            )
        SQL);

        // Organizers
        $this->addSql(<<<'SQL'
            CREATE TABLE organizer (
                id              SERIAL PRIMARY KEY,
                user_id         INT          NOT NULL,
                approval_status VARCHAR(20)  NOT NULL DEFAULT 'pending',
                approved_at     TIMESTAMP(0) WITHOUT TIME ZONE,
                deactivated_at  TIMESTAMP(0) WITHOUT TIME ZONE,
                CONSTRAINT fk_organizer_user FOREIGN KEY (user_id) REFERENCES users (id),
                CONSTRAINT uq_organizer_user UNIQUE (user_id)
            )
        SQL);

        // Categories
        $this->addSql(<<<'SQL'
            CREATE TABLE category (
                id   SERIAL PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                CONSTRAINT uq_category_name UNIQUE (name)
            )
        SQL);

        // Events
        $this->addSql(<<<'SQL'
            CREATE TABLE events (
                id                SERIAL PRIMARY KEY,
                organizer_id      INT          NOT NULL,
                category_id       INT          NOT NULL,
                name              VARCHAR(255) NOT NULL,
                description       TEXT,
                date_time         TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                venue_name        VARCHAR(255) NOT NULL,
                venue_address     VARCHAR(500),
                is_online         BOOLEAN      NOT NULL DEFAULT FALSE,
                banner_image_name VARCHAR(255),
                status            VARCHAR(20)  NOT NULL DEFAULT 'active',
                created_at        TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_event_organizer FOREIGN KEY (organizer_id) REFERENCES organizer (id),
                CONSTRAINT fk_event_category  FOREIGN KEY (category_id)  REFERENCES category (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_event_organizer ON events (organizer_id)');
        $this->addSql('CREATE INDEX idx_event_category  ON events (category_id)');
        $this->addSql('CREATE INDEX idx_event_status    ON events (status)');
        $this->addSql('CREATE INDEX idx_event_datetime  ON events (date_time)');

        // Ticket Tiers (with optimistic lock version column)
        $this->addSql(<<<'SQL'
            CREATE TABLE ticket_tier (
                id            SERIAL PRIMARY KEY,
                event_id      INT          NOT NULL,
                name          VARCHAR(100) NOT NULL,
                base_price    INT          NOT NULL,
                total_seats   INT          NOT NULL,
                sold_count    INT          NOT NULL DEFAULT 0,
                version       INT          NOT NULL DEFAULT 1,
                sale_starts_at TIMESTAMP(0) WITHOUT TIME ZONE,
                sale_ends_at   TIMESTAMP(0) WITHOUT TIME ZONE,
                CONSTRAINT fk_tier_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
            )
        SQL);
        $this->addSql('CREATE INDEX idx_tier_event ON ticket_tier (event_id)');

        // Seats (numbered seating — optional advanced feature)
        $this->addSql(<<<'SQL'
            CREATE TABLE seat (
                id            SERIAL PRIMARY KEY,
                event_id      INT         NOT NULL,
                ticket_tier_id INT        NOT NULL,
                seat_number   VARCHAR(20) NOT NULL,
                is_assigned   BOOLEAN     NOT NULL DEFAULT FALSE,
                CONSTRAINT fk_seat_event FOREIGN KEY (event_id)       REFERENCES events (id) ON DELETE CASCADE,
                CONSTRAINT fk_seat_tier  FOREIGN KEY (ticket_tier_id) REFERENCES ticket_tier (id),
                CONSTRAINT uq_seat_event UNIQUE (event_id, seat_number)
            )
        SQL);

        // Seat Reservations (cart items — expire after 10 min)
        $this->addSql(<<<'SQL'
            CREATE TABLE seat_reservation (
                id              SERIAL PRIMARY KEY,
                user_id         INT          NOT NULL,
                ticket_tier_id  INT          NOT NULL,
                quantity        INT          NOT NULL,
                reserved_at     TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                expires_at      TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                status          VARCHAR(20)  NOT NULL DEFAULT 'pending',
                CONSTRAINT fk_reservation_user FOREIGN KEY (user_id)        REFERENCES users (id),
                CONSTRAINT fk_reservation_tier FOREIGN KEY (ticket_tier_id) REFERENCES ticket_tier (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_reservation_status_expires ON seat_reservation (status, expires_at)');
        $this->addSql('CREATE INDEX idx_reservation_user ON seat_reservation (user_id)');

        // Bookings
        $this->addSql(<<<'SQL'
            CREATE TABLE booking (
                id               SERIAL PRIMARY KEY,
                user_id          INT         NOT NULL,
                event_id         INT         NOT NULL,
                total_credits    INT         NOT NULL,
                status           VARCHAR(20) NOT NULL DEFAULT 'confirmed',
                idempotency_key  VARCHAR(64) NOT NULL,
                created_at       TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_booking_user  FOREIGN KEY (user_id)  REFERENCES users (id),
                CONSTRAINT fk_booking_event FOREIGN KEY (event_id) REFERENCES events (id),
                CONSTRAINT uq_booking_idempotency UNIQUE (idempotency_key)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_booking_user  ON booking (user_id)');
        $this->addSql('CREATE INDEX idx_booking_event ON booking (event_id)');

        // Booking Items
        $this->addSql(<<<'SQL'
            CREATE TABLE booking_item (
                id                  SERIAL PRIMARY KEY,
                booking_id          INT NOT NULL,
                ticket_tier_id      INT NOT NULL,
                seat_reservation_id INT,
                quantity            INT NOT NULL,
                unit_price          INT NOT NULL,
                CONSTRAINT fk_item_booking     FOREIGN KEY (booking_id)          REFERENCES booking (id) ON DELETE CASCADE,
                CONSTRAINT fk_item_tier        FOREIGN KEY (ticket_tier_id)      REFERENCES ticket_tier (id),
                CONSTRAINT fk_item_reservation FOREIGN KEY (seat_reservation_id) REFERENCES seat_reservation (id),
                CONSTRAINT uq_item_reservation UNIQUE (seat_reservation_id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_item_booking ON booking_item (booking_id)');

        // E-Tickets
        $this->addSql(<<<'SQL'
            CREATE TABLE e_ticket (
                id              SERIAL PRIMARY KEY,
                booking_item_id INT          NOT NULL,
                qr_token        VARCHAR(64)  NOT NULL,
                file_path       VARCHAR(500) NOT NULL,
                generated_at    TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_eticket_item FOREIGN KEY (booking_item_id) REFERENCES booking_item (id),
                CONSTRAINT uq_eticket_item     UNIQUE (booking_item_id),
                CONSTRAINT uq_eticket_qrtoken  UNIQUE (qr_token)
            )
        SQL);

        // Transactions (credit ledger)
        $this->addSql(<<<'SQL'
            CREATE TABLE transaction (
                id          SERIAL PRIMARY KEY,
                user_id     INT          NOT NULL,
                amount      INT          NOT NULL,
                type        VARCHAR(20)  NOT NULL,
                reference   VARCHAR(255),
                created_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT fk_transaction_user FOREIGN KEY (user_id) REFERENCES users (id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_transaction_user ON transaction (user_id)');

        // Doctrine Migrations tracking table is created automatically.
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS transaction');
        $this->addSql('DROP TABLE IF EXISTS e_ticket');
        $this->addSql('DROP TABLE IF EXISTS booking_item');
        $this->addSql('DROP TABLE IF EXISTS booking');
        $this->addSql('DROP TABLE IF EXISTS seat_reservation');
        $this->addSql('DROP TABLE IF EXISTS seat');
        $this->addSql('DROP TABLE IF EXISTS ticket_tier');
        $this->addSql('DROP TABLE IF EXISTS events');
        $this->addSql('DROP TABLE IF EXISTS category');
        $this->addSql('DROP TABLE IF EXISTS organizer');
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
