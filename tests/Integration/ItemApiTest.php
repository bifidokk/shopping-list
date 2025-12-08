<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\UserFixtures;

class ItemApiTest extends ApiTestCase
{
    private function getShoppingListId(): int
    {
        $response = $this->makeAuthenticatedRequest(
            'GET',
            '/api/shopping-lists',
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $lists = $this->assertJsonResponse($response, 200);

        return $lists[0]['id'];
    }

    public function testGetItemsReturnsAllItems(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/items",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data);
        $this->assertCount(2, $data); // Milk and Bread from fixtures

        $item = $data[0];
        $this->assertArrayHasKey('id', $item);
        $this->assertArrayHasKey('name', $item);
        $this->assertArrayHasKey('quantity', $item);
        $this->assertArrayHasKey('unit', $item);
        $this->assertArrayHasKey('notes', $item);
        $this->assertArrayHasKey('isDone', $item);
        $this->assertArrayHasKey('createdAt', $item);
        $this->assertArrayHasKey('updatedAt', $item);
    }

    public function testGetItemsFromNonExistentList(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'GET',
            '/api/shopping-lists/999999/items',
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testCreateItem(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/items",
            [
                'name' => 'Cheese',
                'quantity' => 3,
                'unit' => 'blocks',
                'notes' => 'Cheddar preferred',
                'isDone' => false,
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Cheese', $data['name']);
        $this->assertSame(3, $data['quantity']);
        $this->assertSame('blocks', $data['unit']);
        $this->assertSame('Cheddar preferred', $data['notes']);
        $this->assertFalse($data['isDone']);
    }

    public function testCreateItemWithMinimalData(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/items",
            [
                'name' => 'Simple Item',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 201);

        $this->assertSame('Simple Item', $data['name']);
        $this->assertNull($data['quantity']);
        $this->assertNull($data['unit']);
        $this->assertNull($data['notes']);
        $this->assertFalse($data['isDone']);
    }

    public function testCreateItemValidationFails(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/items",
            [
                'quantity' => 5,
                'notes' => 'Missing name',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 400);

        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Validation failed', $data['error']);
    }

    public function testGetSingleItem(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/items",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $items = $this->assertJsonResponse($response, 200);
        $itemId = $items[0]['id'];

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/items/{$itemId}",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertSame($itemId, $data['id']);
        $this->assertArrayHasKey('name', $data);
    }

    public function testGetSingleItemNotFound(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/items/999999",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 404);

        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Item not found', $data['error']);
    }

    public function testUpdateItem(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/items",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $items = $this->assertJsonResponse($response, 200);
        $itemId = $items[0]['id'];

        $response = $this->makeAuthenticatedRequest(
            'PUT',
            "/api/shopping-lists/{$listId}/items/{$itemId}",
            [
                'name' => 'Updated Item',
                'quantity' => 10,
                'unit' => 'pieces',
                'notes' => 'Updated notes',
                'isDone' => true,
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertSame($itemId, $data['id']);
        $this->assertSame('Updated Item', $data['name']);
        $this->assertSame(10, $data['quantity']);
        $this->assertSame('pieces', $data['unit']);
        $this->assertSame('Updated notes', $data['notes']);
        $this->assertTrue($data['isDone']);
    }

    public function testUpdateItemPartial(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/items",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $items = $this->assertJsonResponse($response, 200);
        $itemId = $items[0]['id'];
        $originalName = $items[0]['name'];

        $response = $this->makeAuthenticatedRequest(
            'PATCH',
            "/api/shopping-lists/{$listId}/items/{$itemId}",
            [
                'quantity' => 5,
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertSame($originalName, $data['name']); // Name unchanged
        $this->assertSame(5, $data['quantity']); // Quantity updated
    }

    public function testUpdateItemNotFound(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'PUT',
            "/api/shopping-lists/{$listId}/items/999999",
            [
                'name' => 'Updated',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 404);
        $this->assertArrayHasKey('error', $data);
    }

    public function testDeleteItem(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/items",
            [
                'name' => 'To Be Deleted',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 201);
        $itemId = $data['id'];

        $response = $this->makeAuthenticatedRequest(
            'DELETE',
            "/api/shopping-lists/{$listId}/items/{$itemId}",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $this->assertSame(204, $response->getStatusCode());

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/items/{$itemId}",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteItemNotFound(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'DELETE',
            "/api/shopping-lists/{$listId}/items/999999",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 404);
        $this->assertArrayHasKey('error', $data);
    }

    public function testToggleItemDone(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/items",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $items = $this->assertJsonResponse($response, 200);
        $item = $items[0];
        $itemId = $item['id'];
        $originalStatus = $item['isDone'];

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/items/{$itemId}/toggle",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertSame($itemId, $data['id']);
        $this->assertSame(!$originalStatus, $data['isDone']);

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/items/{$itemId}/toggle",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);
        $this->assertSame($originalStatus, $data['isDone']);
    }

    public function testToggleItemNotFound(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/items/999999/toggle",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 404);
        $this->assertArrayHasKey('error', $data);
    }

    public function testCannotAccessItemsFromDifferentUsersList(): void
    {
        $listId = $this->getShoppingListId();

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/items",
            [],
            UserFixtures::USER_TELEGRAM_ID + 1
        );

        $this->assertSame(404, $response->getStatusCode());
    }
}
