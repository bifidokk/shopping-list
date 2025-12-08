<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\UserFixtures;
use App\Entity\ShoppingList;

class ShoppingListApiTest extends ApiTestCase
{
    public function testGetShoppingListsReturnsUserLists(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'GET',
            '/api/shopping-lists',
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);

        $shoppingList = $data[0];
        $this->assertArrayHasKey('id', $shoppingList);
        $this->assertArrayHasKey('name', $shoppingList);
        $this->assertArrayHasKey('description', $shoppingList);
        $this->assertArrayHasKey('createdAt', $shoppingList);
        $this->assertArrayHasKey('updatedAt', $shoppingList);

        $this->assertSame('Test Shopping List', $shoppingList['name']);
        $this->assertSame('A test shopping list for integration tests', $shoppingList['description']);

        $listFromDb = $this->entityManager
            ->getRepository(ShoppingList::class)
            ->find($shoppingList['id']);

        $this->assertNotNull($listFromDb);
        $this->assertSame('Test Shopping List', $listFromDb->getName());
    }

    public function testGetShoppingListsReturnsEmptyForDifferentUser(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'GET',
            '/api/shopping-lists',
            [],
            UserFixtures::USER_TELEGRAM_ID + 1
        );

        $data = $this->assertJsonResponse($response, 200);
        $this->assertIsArray($data);
        $this->assertCount(0, $data);
    }

    public function testGetShoppingListsRequiresAuthentication(): void
    {
        $this->client->request('GET', '/api/shopping-lists');
        $response = $this->client->getResponse();

        $this->assertSame(401, $response->getStatusCode());
    }

    public function testCreateShoppingList(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'POST',
            '/api/shopping-lists',
            [
                'name' => 'New Shopping List',
                'description' => 'A newly created list',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertSame('New Shopping List', $data['name']);
        $this->assertSame('A newly created list', $data['description']);
        $this->assertArrayHasKey('createdAt', $data);
        $this->assertArrayHasKey('updatedAt', $data);
    }

    public function testCreateShoppingListWithoutDescription(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'POST',
            '/api/shopping-lists',
            [
                'name' => 'Minimal List',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 201);

        $this->assertSame('Minimal List', $data['name']);
        $this->assertNull($data['description']);
    }

    public function testCreateShoppingListValidationFails(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'POST',
            '/api/shopping-lists',
            [
                'description' => 'Missing name field',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 400);

        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Validation failed', $data['error']);
    }

    public function testGetSingleShoppingList(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'GET',
            '/api/shopping-lists',
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $lists = $this->assertJsonResponse($response, 200);
        $listId = $lists[0]['id'];

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertSame($listId, $data['id']);
        $this->assertSame('Test Shopping List', $data['name']);
        $this->assertArrayHasKey('items', $data);
        $this->assertIsArray($data['items']);
        $this->assertCount(2, $data['items']); // Milk and Bread from fixtures
    }

    public function testGetSingleShoppingListNotFound(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'GET',
            '/api/shopping-lists/999999',
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 404);

        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Shopping list not found', $data['error']);
    }

    public function testGetSingleShoppingListOfDifferentUser(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'GET',
            '/api/shopping-lists',
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $lists = $this->assertJsonResponse($response, 200);
        $listId = $lists[0]['id'];

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}",
            [],
            UserFixtures::USER_TELEGRAM_ID + 1
        );

        $data = $this->assertJsonResponse($response, 404);
        $this->assertArrayHasKey('error', $data);
    }

    public function testUpdateShoppingList(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'GET',
            '/api/shopping-lists',
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $lists = $this->assertJsonResponse($response, 200);
        $listId = $lists[0]['id'];

        $response = $this->makeAuthenticatedRequest(
            'PUT',
            "/api/shopping-lists/{$listId}",
            [
                'name' => 'Updated List Name',
                'description' => 'Updated description',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertSame($listId, $data['id']);
        $this->assertSame('Updated List Name', $data['name']);
        $this->assertSame('Updated description', $data['description']);
    }

    public function testUpdateShoppingListPartial(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'GET',
            '/api/shopping-lists',
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $lists = $this->assertJsonResponse($response, 200);
        $listId = $lists[0]['id'];

        $response = $this->makeAuthenticatedRequest(
            'PATCH',
            "/api/shopping-lists/{$listId}",
            [
                'name' => 'Partially Updated',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertSame('Partially Updated', $data['name']);
        $this->assertSame('A test shopping list for integration tests', $data['description']);
    }

    public function testUpdateShoppingListNotFound(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'PUT',
            '/api/shopping-lists/999999',
            [
                'name' => 'Updated',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 404);
        $this->assertArrayHasKey('error', $data);
    }

    public function testDeleteShoppingList(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'POST',
            '/api/shopping-lists',
            [
                'name' => 'To Be Deleted',
            ],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 201);
        $listId = $data['id'];

        $response = $this->makeAuthenticatedRequest(
            'DELETE',
            "/api/shopping-lists/{$listId}",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $this->assertSame(204, $response->getStatusCode());

        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    public function testDeleteShoppingListNotFound(): void
    {
        $response = $this->makeAuthenticatedRequest(
            'DELETE',
            '/api/shopping-lists/999999',
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 404);
        $this->assertArrayHasKey('error', $data);
    }
}
