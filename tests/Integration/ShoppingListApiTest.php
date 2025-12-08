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
        // Use a different user ID than the fixture user
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
}
