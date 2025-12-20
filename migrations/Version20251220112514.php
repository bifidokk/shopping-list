<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251220112514 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user_default_lists table for per-user default list preferences';
    }

    public function up(Schema $schema): void
    {
        // Create user_default_lists table
        $this->addSql('CREATE TABLE user_default_lists (
            user_id INT PRIMARY KEY,
            shopping_list_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL,
            CONSTRAINT fk_user_default_lists_user
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            CONSTRAINT fk_user_default_lists_shopping_list
                FOREIGN KEY (shopping_list_id) REFERENCES shopping_lists(id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE INDEX idx_user_default_lists_shopping_list ON user_default_lists(shopping_list_id)');

        // Backfill: For each owner who has a default list, create a user_default_lists entry
        // Only migrate lists where is_default = true and the user is the owner
        $this->addSql("INSERT INTO user_default_lists (user_id, shopping_list_id, created_at, updated_at)
            SELECT owner_id, id, NOW(), NOW()
            FROM shopping_lists
            WHERE is_default = true");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_default_lists');
    }
}
