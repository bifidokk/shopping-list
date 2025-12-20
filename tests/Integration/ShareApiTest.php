<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\UserFixtures;
use App\Entity\ListShare;
use App\Entity\ShoppingList;

class ShareApiTest extends ApiTestCase
{
    private function getTestListId(): int
    {
        $list = $this->entityManager
            ->getRepository(ShoppingList::class)
            ->findOneBy(['name' => 'Test Shopping List']);

        $this->assertInstanceOf(ShoppingList::class, $list);

        return $list->getId();
    }

    public function testCreateShareWithValidUsername(): void
    {
        $listId = $this->getTestListId();

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('listId', $data);
        $this->assertArrayHasKey('ownerId', $data);
        $this->assertArrayHasKey('sharedWithUserId', $data);
        $this->assertArrayHasKey('sharedWithUsername', $data);
        $this->assertArrayHasKey('sharedWithFirstName', $data);
        $this->assertArrayHasKey('sharedWithLastName', $data);
        $this->assertArrayHasKey('createdAt', $data);

        $this->assertSame($listId, $data['listId']);
        $this->assertSame('testuser2', $data['sharedWithUsername']);
        $this->assertSame('Second', $data['sharedWithFirstName']);
        $this->assertSame('User', $data['sharedWithLastName']);

        // Verify in database
        $share = $this->entityManager
            ->getRepository(ListShare::class)
            ->find($data['id']);

        $this->assertInstanceOf(ListShare::class, $share);
        $this->assertSame($listId, $share->getShoppingList()->getId());
        $this->assertSame('testuser2', $share->getSharedWithUser()->getUsername());
    }

    public function testCreateShareWithAtPrefixUsername(): void
    {
        $listId = $this->getTestListId();

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => '@testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 201);

        $this->assertSame('testuser2', $data['sharedWithUsername']);
    }

    public function testCreateShareWithInvalidUsername(): void
    {
        $listId = $this->getTestListId();

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'nonexistent_user_12345'],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 404);

        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('not found', $data['error']);
    }

    public function testCreateShareAsNonOwner(): void
    {
        $listId = $this->getTestListId();

        // User 2 tries to share User 1's list
        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser'],
            UserFixtures::USER_2_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 403);

        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('owner', strtolower($data['error']));
    }

    public function testCreateDuplicateShare(): void
    {
        $listId = $this->getTestListId();

        // First share
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // Attempt duplicate share
        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 409);

        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('already shared', $data['error']);
    }

    public function testCreateShareWithSelf(): void
    {
        $listId = $this->getTestListId();

        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser'],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 409);

        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('yourself', $data['error']);
    }

    public function testListShares(): void
    {
        $listId = $this->getTestListId();

        // Create a share first
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // List shares
        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/shares",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);

        $share = $data[0];
        $this->assertArrayHasKey('id', $share);
        $this->assertArrayHasKey('listId', $share);
        $this->assertArrayHasKey('sharedWithUsername', $share);
        $this->assertSame($listId, $share['listId']);
        $this->assertSame('testuser2', $share['sharedWithUsername']);
    }

    public function testListSharesAsCollaborator(): void
    {
        $listId = $this->getTestListId();

        // Owner shares list with user 2
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // User 2 (collaborator) lists shares
        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/shares",
            [],
            UserFixtures::USER_2_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data);
        $this->assertCount(1, $data);
    }

    public function testListSharesAsNonMember(): void
    {
        $listId = $this->getTestListId();

        // User 2 tries to list shares without being owner or collaborator
        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}/shares",
            [],
            UserFixtures::USER_2_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 403);

        $this->assertArrayHasKey('error', $data);
    }

    public function testRemoveShareAsOwner(): void
    {
        $listId = $this->getTestListId();

        // Create share
        $createResponse = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        $shareData = $this->assertJsonResponse($createResponse, 201);
        $sharedUserId = $shareData['sharedWithUserId'];

        // Owner removes share
        $response = $this->makeAuthenticatedRequest(
            'DELETE',
            "/api/shopping-lists/{$listId}/shares/{$sharedUserId}",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $this->assertSame(204, $response->getStatusCode());

        // Verify share is removed from database
        $share = $this->entityManager
            ->getRepository(ListShare::class)
            ->find($shareData['id']);

        $this->assertNull($share);
    }

    public function testRemoveShareAsCollaborator(): void
    {
        $listId = $this->getTestListId();

        // Owner shares list with user 2
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // Get user 2's ID
        $user2 = $this->entityManager
            ->getRepository(\App\Entity\User::class)
            ->findOneBy(['telegramId' => UserFixtures::USER_2_TELEGRAM_ID]);

        // User 2 (collaborator) removes themselves
        $response = $this->makeAuthenticatedRequest(
            'DELETE',
            "/api/shopping-lists/{$listId}/shares/{$user2->getId()}",
            [],
            UserFixtures::USER_2_TELEGRAM_ID
        );

        $this->assertSame(204, $response->getStatusCode());
    }

    public function testRemoveShareAsNonOwner(): void
    {
        $listId = $this->getTestListId();

        // Owner shares list with both user 2
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // Create a third user to test removing another collaborator
        $user3 = new \App\Entity\User();
        $user3->setTelegramId(555666777);
        $user3->setUsername('testuser3');
        $user3->setFirstName('Third');
        $user3->setLastName('User');
        $this->entityManager->persist($user3);
        $this->entityManager->flush();

        // Owner shares list with user 3
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser3'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // User 2 (collaborator) tries to remove user 3 (another collaborator) - not allowed
        $response = $this->makeAuthenticatedRequest(
            'DELETE',
            "/api/shopping-lists/{$listId}/shares/{$user3->getId()}",
            [],
            UserFixtures::USER_2_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 403);

        $this->assertArrayHasKey('error', $data);
    }

    public function testCollaboratorCanViewList(): void
    {
        $listId = $this->getTestListId();

        // Share list with user 2
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // User 2 views the list
        $response = $this->makeAuthenticatedRequest(
            'GET',
            "/api/shopping-lists/{$listId}",
            [],
            UserFixtures::USER_2_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertArrayHasKey('id', $data);
        $this->assertSame($listId, $data['id']);
        $this->assertSame('Test Shopping List', $data['name']);
    }

    public function testCollaboratorCanEditList(): void
    {
        $listId = $this->getTestListId();

        // Share list with user 2
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // User 2 updates the list
        $response = $this->makeAuthenticatedRequest(
            'PUT',
            "/api/shopping-lists/{$listId}",
            [
                'name' => 'Updated by Collaborator',
                'description' => 'New description',
            ],
            UserFixtures::USER_2_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertSame('Updated by Collaborator', $data['name']);
        $this->assertSame('New description', $data['description']);
    }

    public function testCollaboratorCannotDeleteList(): void
    {
        $listId = $this->getTestListId();

        // Share list with user 2
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // User 2 tries to delete the list
        $response = $this->makeAuthenticatedRequest(
            'DELETE',
            "/api/shopping-lists/{$listId}",
            [],
            UserFixtures::USER_2_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 403);

        $this->assertArrayHasKey('error', $data);
        $this->assertStringContainsString('owner', strtolower($data['error']));

        // Verify list still exists
        $list = $this->entityManager
            ->getRepository(ShoppingList::class)
            ->find($listId);

        $this->assertInstanceOf(ShoppingList::class, $list);
    }

    public function testCollaboratorCanAddItems(): void
    {
        $listId = $this->getTestListId();

        // Share list with user 2
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // User 2 adds an item
        $response = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/items",
            [
                'name' => 'Item added by collaborator',
                'quantity' => 1,
            ],
            UserFixtures::USER_2_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 201);

        $this->assertArrayHasKey('id', $data);
        $this->assertSame('Item added by collaborator', $data['name']);
    }

    public function testSharedListAppearsInCollaboratorLists(): void
    {
        $listId = $this->getTestListId();

        // Share list with user 2
        $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        // User 2 gets their lists
        $response = $this->makeAuthenticatedRequest(
            'GET',
            '/api/shopping-lists',
            [],
            UserFixtures::USER_2_TELEGRAM_ID
        );

        $data = $this->assertJsonResponse($response, 200);

        $this->assertIsArray($data);
        $this->assertGreaterThanOrEqual(1, count($data));

        // Find the shared list
        $sharedList = null;
        foreach ($data as $list) {
            if ($list['id'] === $listId) {
                $sharedList = $list;

                break;
            }
        }

        $this->assertNotNull($sharedList, 'Shared list should appear in collaborator\'s list');
        $this->assertSame('Test Shopping List', $sharedList['name']);
        $this->assertArrayHasKey('ownerId', $sharedList);
        $this->assertArrayHasKey('isOwner', $sharedList);
        $this->assertArrayHasKey('sharedWith', $sharedList);
        $this->assertFalse($sharedList['isOwner']); // User 2 is not the owner
        $this->assertSame(1, $sharedList['sharedWith']); // Shared with 1 user (user 2)
    }

    public function testDeleteListCascadesShares(): void
    {
        $listId = $this->getTestListId();

        // Share list with user 2
        $createResponse = $this->makeAuthenticatedRequest(
            'POST',
            "/api/shopping-lists/{$listId}/shares",
            ['telegramUsername' => 'testuser2'],
            UserFixtures::USER_TELEGRAM_ID
        );

        $shareData = $this->assertJsonResponse($createResponse, 201);
        $shareId = $shareData['id'];

        // Owner deletes the list
        $response = $this->makeAuthenticatedRequest(
            'DELETE',
            "/api/shopping-lists/{$listId}",
            [],
            UserFixtures::USER_TELEGRAM_ID
        );

        $this->assertSame(204, $response->getStatusCode());

        // Verify list is deleted
        $list = $this->entityManager
            ->getRepository(ShoppingList::class)
            ->find($listId);

        $this->assertNull($list);

        // Verify share is also deleted (CASCADE)
        $share = $this->entityManager
            ->getRepository(ListShare::class)
            ->find($shareId);

        $this->assertNull($share);
    }
}
