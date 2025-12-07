# Shopping List Backend API

A REST API backend for a shopping list application designed for Telegram Mini Apps. Built with Symfony 8.0, PHP 8.4+, and PostgreSQL.

## Features

- **Telegram Mini App Authentication**: Secure authentication using Telegram's initData validation
- **Shopping Lists Management**: Create, read, update, and delete shopping lists
- **Items Management**: Full CRUD operations for items with support for quantities, units, notes, and completion status
- **User Isolation**: Each user only sees their own shopping lists and items
- **PostgreSQL Database**: Robust relational database with proper foreign key constraints

## Setup

### Prerequisites

- PHP 8.4 or higher
- PostgreSQL 16 or higher
- Composer
- A Telegram Bot (get token from [@BotFather](https://t.me/botfather))

### Installation

1. Clone the repository and install dependencies:
```bash
composer install
```

2. Configure environment variables in `.env`:
```env
DATABASE_URL="postgresql://username:password@127.0.0.1:5432/shopping_list?serverVersion=16&charset=utf8"
TELEGRAM_BOT_TOKEN=your_bot_token_here
APP_SECRET=your_generated_secret_key
```

3. Create database and run migrations:
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

4. Start the development server:
```bash
symfony serve
# or
php -S localhost:8000 -t public
```

## API Usage

### Authentication

All API endpoints require authentication via Telegram Mini App initData. Include the following header with every request:

```
X-Telegram-Init-Data: query_id=...&user=...&auth_date=...&hash=...
```

The backend validates this data using your bot token to ensure requests are authentic.

### Endpoints

#### Shopping Lists

**List all shopping lists**
```http
GET /api/shopping-lists
```

**Create shopping list**
```http
POST /api/shopping-lists
Content-Type: application/json

{
  "name": "Grocery Shopping",
  "description": "Weekly groceries"
}
```

**Get shopping list with items**
```http
GET /api/shopping-lists/{id}
```

**Update shopping list**
```http
PUT /api/shopping-lists/{id}
Content-Type: application/json

{
  "name": "Updated Name",
  "description": "Updated description"
}
```

**Delete shopping list**
```http
DELETE /api/shopping-lists/{id}
```

#### Items

**List items in a shopping list**
```http
GET /api/shopping-lists/{listId}/items
```

**Create item**
```http
POST /api/shopping-lists/{listId}/items
Content-Type: application/json

{
  "name": "Milk",
  "quantity": 2,
  "unit": "liters",
  "notes": "Organic if possible",
  "isDone": false
}
```

**Get single item**
```http
GET /api/shopping-lists/{listId}/items/{id}
```

**Update item**
```http
PUT /api/shopping-lists/{listId}/items/{id}
Content-Type: application/json

{
  "name": "Milk",
  "quantity": 3,
  "isDone": true
}
```

**Delete item**
```http
DELETE /api/shopping-lists/{listId}/items/{id}
```

**Toggle item completion status**
```http
POST /api/shopping-lists/{listId}/items/{id}/toggle
```

## Development

### Database Commands

```bash
# Create new migration
php bin/console doctrine:migrations:diff

# Execute migrations
php bin/console doctrine:migrations:migrate

# Rollback migration
php bin/console doctrine:migrations:migrate prev
```

### Debugging

```bash
# List all routes
php bin/console debug:router

# Check container services
php bin/console debug:container

# Clear cache
php bin/console cache:clear
```

## Project Structure

```
src/
├── Controller/          # API controllers
│   ├── ItemController.php
│   └── ShoppingListController.php
├── Entity/             # Doctrine entities
│   ├── Item.php
│   ├── ShoppingList.php
│   └── User.php
├── Security/           # Authentication
│   └── TelegramAuthenticator.php
└── Service/            # Business logic
    └── TelegramAuthService.php

config/
├── packages/           # Bundle configurations
└── services.yaml       # Service definitions

migrations/             # Database migrations
```

## License

Proprietary
