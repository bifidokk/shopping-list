<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251207000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema for shopping list application';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            telegram_id BIGINT NOT NULL UNIQUE,
            first_name VARCHAR(255) DEFAULT NULL,
            last_name VARCHAR(255) DEFAULT NULL,
            username VARCHAR(255) DEFAULT NULL,
            language_code VARCHAR(10) DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL
        )');

        $this->addSql('CREATE TABLE shopping_lists (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL,
            CONSTRAINT fk_shopping_lists_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE INDEX idx_shopping_lists_user ON shopping_lists(user_id)');

        $this->addSql('CREATE TABLE items (
            id SERIAL PRIMARY KEY,
            shopping_list_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            quantity INTEGER DEFAULT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            is_done BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP NOT NULL,
            updated_at TIMESTAMP NOT NULL,
            CONSTRAINT fk_items_shopping_list FOREIGN KEY (shopping_list_id) REFERENCES shopping_lists(id) ON DELETE CASCADE
        )');

        $this->addSql('CREATE INDEX idx_items_shopping_list ON items(shopping_list_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE items');
        $this->addSql('DROP TABLE shopping_lists');
        $this->addSql('DROP TABLE users');
    }
}
