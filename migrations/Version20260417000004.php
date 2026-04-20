<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417000004 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add name field to users for profile editing';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD name VARCHAR(120) DEFAULT NULL");
        $this->addSql("UPDATE users SET name = INITCAP(SPLIT_PART(email, '@', 1)) WHERE name IS NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users DROP name");
    }
}
