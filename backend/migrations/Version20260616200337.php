<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 9.2: Replace float total with MoneyEmbeddable (bigint cents + currency).
 *
 * Converts existing DOUBLE PRECISION total to BIGINT cents via ROUND(total*100),
 * adds currency VARCHAR(3) column defaulting to 'USD'.
 *
 * IMPORTANT: The down() migration is IRREVERSIBLE — converting cents back to float
 * loses sub-cent precision. Use only for dev rollbacks, never in production.
 */
final class Version20260616200337 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Phase 9.2: Replace float total with MoneyEmbeddable (bigint cents + currency)';
    }

    public function up(Schema $schema): void
    {
        // Add new columns (no prefix, matching columnPrefix: false on Embeddable)
        $this->addSql('ALTER TABLE orders ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT \'USD\'');
        $this->addSql('ALTER TABLE orders ADD COLUMN amount BIGINT');

        // Backfill: convert float dollars to integer cents
        $this->addSql('UPDATE orders SET amount = CAST(ROUND(total * 100) AS BIGINT)');

        // Drop old float column, make amount NOT NULL
        $this->addSql('ALTER TABLE orders DROP COLUMN total');
        $this->addSql('ALTER TABLE orders ALTER COLUMN amount SET NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // DEV ONLY: cents / 100.0 loses sub-cent precision.
        $this->addSql('ALTER TABLE orders ADD COLUMN total DOUBLE PRECISION');
        $this->addSql('UPDATE orders SET total = amount / 100.0');
        $this->addSql('ALTER TABLE orders DROP COLUMN amount');
        $this->addSql('ALTER TABLE orders DROP COLUMN currency');
    }
}