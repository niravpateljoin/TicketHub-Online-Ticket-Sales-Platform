<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make e_ticket.file_path and e_ticket.generated_at nullable.
 *
 * ETicket rows are now created immediately at checkout time with null values;
 * the PDF is generated asynchronously (via Messenger or synchronously as a fallback).
 */
final class Version20260417000006 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make e_ticket.file_path and generated_at nullable for async PDF generation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE e_ticket ALTER COLUMN file_path DROP NOT NULL');
        $this->addSql('ALTER TABLE e_ticket ALTER COLUMN generated_at DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Re-applying NOT NULL requires all existing rows to have values first.
        $this->addSql("UPDATE e_ticket SET file_path = '' WHERE file_path IS NULL");
        $this->addSql('UPDATE e_ticket SET generated_at = NOW() WHERE generated_at IS NULL');
        $this->addSql('ALTER TABLE e_ticket ALTER COLUMN file_path SET NOT NULL');
        $this->addSql('ALTER TABLE e_ticket ALTER COLUMN generated_at SET NOT NULL');
    }
}
