<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417000003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add administrator email verification fields to users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD is_verified BOOLEAN NOT NULL DEFAULT TRUE");
        $this->addSql("ALTER TABLE users ADD verified_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL");
        $this->addSql("ALTER TABLE users ADD verification_token VARCHAR(64) DEFAULT NULL");
        $this->addSql("CREATE UNIQUE INDEX UNIQ_USERS_VERIFICATION_TOKEN ON users (verification_token)");
        $this->addSql("UPDATE users SET verified_at = created_at WHERE is_verified = TRUE AND verified_at IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DROP INDEX UNIQ_USERS_VERIFICATION_TOKEN");
        $this->addSql("ALTER TABLE users DROP verification_token");
        $this->addSql("ALTER TABLE users DROP verified_at");
        $this->addSql("ALTER TABLE users DROP is_verified");
    }
}
