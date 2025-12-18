<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add isDefault column to shopping_lists table
 */
final class Version20251218193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isDefault column to shopping_lists table with partial unique index';
    }

    public function up(Schema $schema): void
    {
        // Add column with default false
        $this->addSql('ALTER TABLE shopping_lists ADD COLUMN is_default BOOLEAN NOT NULL DEFAULT FALSE');

        // Create partial unique index to ensure only one default list per user
        $this->addSql('CREATE UNIQUE INDEX uniq_user_default_list ON shopping_lists (user_id) WHERE is_default = true');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_user_default_list');
        $this->addSql('ALTER TABLE shopping_lists DROP COLUMN is_default');
    }
}
