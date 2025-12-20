<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251219205045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add shopping list sharing functionality: list_shares table, owner_id, and shared_with count';
    }

    public function up(Schema $schema): void
    {
        // Create list_shares table
        $this->addSql('CREATE TABLE list_shares (
            id SERIAL PRIMARY KEY,
            shopping_list_id INT NOT NULL,
            owner_id INT NOT NULL,
            shared_with_user_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL,
            CONSTRAINT fk_list_shares_shopping_list
                FOREIGN KEY (shopping_list_id) REFERENCES shopping_lists(id) ON DELETE CASCADE,
            CONSTRAINT fk_list_shares_owner
                FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_list_shares_shared_with
                FOREIGN KEY (shared_with_user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT uniq_list_user UNIQUE (shopping_list_id, shared_with_user_id)
        )');

        // Create indexes for performance
        $this->addSql('CREATE INDEX idx_list_shares_shopping_list ON list_shares(shopping_list_id)');
        $this->addSql('CREATE INDEX idx_list_shares_shared_with ON list_shares(shared_with_user_id)');
        $this->addSql('CREATE INDEX idx_list_shares_owner ON list_shares(owner_id)');

        // Add owner_id to shopping_lists (nullable first for backfill)
        $this->addSql('ALTER TABLE shopping_lists ADD COLUMN owner_id INT');
        $this->addSql('ALTER TABLE shopping_lists ADD CONSTRAINT fk_shopping_lists_owner
            FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE');

        // Backfill: set creator as owner (user_id -> owner_id)
        $this->addSql('UPDATE shopping_lists SET owner_id = user_id WHERE owner_id IS NULL');

        // Make owner_id NOT NULL after backfill
        $this->addSql('ALTER TABLE shopping_lists ALTER COLUMN owner_id SET NOT NULL');

        // Add shared_with count column
        $this->addSql('ALTER TABLE shopping_lists ADD COLUMN shared_with INT NOT NULL DEFAULT 0');
    }

    public function down(Schema $schema): void
    {
        // Drop shared_with column
        $this->addSql('ALTER TABLE shopping_lists DROP COLUMN shared_with');

        // Drop owner_id column and constraint
        $this->addSql('ALTER TABLE shopping_lists DROP CONSTRAINT fk_shopping_lists_owner');
        $this->addSql('ALTER TABLE shopping_lists DROP COLUMN owner_id');

        // Drop indexes
        $this->addSql('DROP INDEX idx_list_shares_owner');
        $this->addSql('DROP INDEX idx_list_shares_shared_with');
        $this->addSql('DROP INDEX idx_list_shares_shopping_list');

        // Drop list_shares table
        $this->addSql('DROP TABLE list_shares');
    }
}
