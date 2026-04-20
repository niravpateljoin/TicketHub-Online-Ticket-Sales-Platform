<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add error_log table for application error tracking.
 */
final class Version20260416000002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create error_log table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE error_log (
            id SERIAL NOT NULL,
            level VARCHAR(20) NOT NULL DEFAULT \'error\',
            message TEXT NOT NULL,
            context JSON DEFAULT NULL,
            route VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('COMMENT ON COLUMN error_log.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE error_log');
    }
}
