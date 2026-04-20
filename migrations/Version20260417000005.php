<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417000005 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add pending admin email field for verified email-change workflow';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD pending_email VARCHAR(180) DEFAULT NULL");
        $this->addSql("CREATE UNIQUE INDEX UNIQ_USERS_PENDING_EMAIL ON users (pending_email)");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP INDEX UNIQ_USERS_PENDING_EMAIL");
        $this->addSql("ALTER TABLE users DROP pending_email");
    }
}
