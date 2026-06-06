<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260606110332 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 8.1: Add processed_by JSON column for handler idempotency';
    }

    public function up(Schema $schema): void
    {
        // Tracks which handlers have processed this order.
        // Default [] means no handler has run yet. Each handler appends its name
        // (notifications, inventory, analytics) before doing its work.
        // Not nullable: there is always a (possibly empty) list.
        $this->addSql('ALTER TABLE orders ADD processed_by JSON DEFAULT \'[]\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE orders DROP processed_by');
    }
}
