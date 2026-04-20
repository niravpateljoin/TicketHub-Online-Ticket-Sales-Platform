<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260417000007 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add unique slug column to events and backfill existing rows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE events ADD slug VARCHAR(180) DEFAULT NULL');

        $rows = $this->connection->fetchAllAssociative('SELECT id, name FROM events ORDER BY id ASC');
        $used = [];

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            $name = (string) ($row['name'] ?? '');
            $base = $this->slugify($name);
            $candidate = $base;
            $suffix = 2;

            while (isset($used[$candidate])) {
                $candidate = $base . '-' . $suffix;
                $suffix++;
            }

            $used[$candidate] = true;
            $this->addSql('UPDATE events SET slug = :slug WHERE id = :id', [
                'slug' => $candidate,
                'id' => $id,
            ]);
        }

        $this->addSql('ALTER TABLE events ALTER COLUMN slug SET NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_EVENTS_SLUG ON events (slug)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_EVENTS_SLUG');
        $this->addSql('ALTER TABLE events DROP slug');
    }

    private function slugify(string $value): string
    {
        $slug = strtolower(trim($value));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'event';
    }
}
