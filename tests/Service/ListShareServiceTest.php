<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ListShare;
use App\Entity\ShoppingList;
use App\Entity\User;
use App\Repository\ListShareRepository;
use App\Repository\UserRepository;
use App\Service\ListShareService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ListShareServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ListShareRepository $shareRepository;
    private UserRepository $userRepository;
    private LoggerInterface $logger;
    private ListShareService $service;
    private User $owner;
    private User $sharedUser;
    private ShoppingList $list;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->shareRepository = $this->createMock(ListShareRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new ListShareService(
            $this->entityManager,
            $this->shareRepository,
            $this->userRepository,
            $this->logger
        );

        $this->owner = new User();
        $this->owner->setTelegramId(123456789);
        $this->owner->setUsername('owner_user');
        // Set ID using reflection
        $reflection = new \ReflectionClass($this->owner);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($this->owner, 1);

        $this->sharedUser = new User();
        $this->sharedUser->setTelegramId(987654321);
        $this->sharedUser->setUsername('shared_user');
        // Set ID using reflection
        $reflection = new \ReflectionClass($this->sharedUser);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($this->sharedUser, 2);

        $this->list = new ShoppingList();
        $this->list->setName('Test List');
        $this->list->setOwner($this->owner);
        $this->list->setUser($this->owner);
        $this->list->setSharedWith(0);
    }

    public function testShareListWithValidUser(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'shared_user'])
            ->willReturn($this->sharedUser);

        $this->shareRepository->expects($this->once())
            ->method('findByListAndUser')
            ->with($this->list, $this->sharedUser)
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (ListShare $share) {
                return $share->getShoppingList() === $this->list
                    && $share->getOwner() === $this->owner
                    && $share->getSharedWithUser() === $this->sharedUser;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->shareList($this->list, 'shared_user', $this->owner);

        $this->assertInstanceOf(ListShare::class, $result);
        $this->assertSame($this->list, $result->getShoppingList());
        $this->assertSame($this->owner, $result->getOwner());
        $this->assertSame($this->sharedUser, $result->getSharedWithUser());
        $this->assertSame(1, $this->list->getSharedWith());
    }

    public function testShareListWithNonExistentUser(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'nonexistent'])
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("User with username '@nonexistent' not found");

        $this->service->shareList($this->list, 'nonexistent', $this->owner);
    }

    public function testShareListWithSelf(): void
    {
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'owner_user'])
            ->willReturn($this->owner);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot share list with yourself');

        $this->service->shareList($this->list, 'owner_user', $this->owner);
    }

    public function testShareAlreadySharedList(): void
    {
        $existingShare = new ListShare();
        $existingShare->setShoppingList($this->list);
        $existingShare->setOwner($this->owner);
        $existingShare->setSharedWithUser($this->sharedUser);

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'shared_user'])
            ->willReturn($this->sharedUser);

        $this->shareRepository->expects($this->once())
            ->method('findByListAndUser')
            ->with($this->list, $this->sharedUser)
            ->willReturn($existingShare);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('List is already shared with this user');

        $this->service->shareList($this->list, 'shared_user', $this->owner);
    }

    public function testUsernameNormalization(): void
    {
        // Test with @ prefix
        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'shared_user'])  // Should strip @
            ->willReturn($this->sharedUser);

        $this->shareRepository->expects($this->once())
            ->method('findByListAndUser')
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->shareList($this->list, '@shared_user', $this->owner);

        $this->assertInstanceOf(ListShare::class, $result);
    }

    public function testRemoveShare(): void
    {
        $this->list->setSharedWith(3);

        $share = new ListShare();
        $share->setShoppingList($this->list);
        $share->setOwner($this->owner);
        $share->setSharedWithUser($this->sharedUser);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($share);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->removeShare($share);

        $this->assertSame(2, $this->list->getSharedWith());
    }

    public function testGetListShares(): void
    {
        $share1 = new ListShare();
        $share1->setShoppingList($this->list);
        $share1->setOwner($this->owner);
        $share1->setSharedWithUser($this->sharedUser);

        $share2 = new ListShare();
        $share2->setShoppingList($this->list);
        $share2->setOwner($this->owner);

        $user3 = new User();
        $user3->setTelegramId(111222333);
        $user3->setUsername('user3');
        $share2->setSharedWithUser($user3);

        $expectedShares = [$share1, $share2];

        $this->shareRepository->expects($this->once())
            ->method('findAllByList')
            ->with($this->list)
            ->willReturn($expectedShares);

        $result = $this->service->getListShares($this->list);

        $this->assertSame($expectedShares, $result);
    }

    public function testGetUserSharedLists(): void
    {
        $list1 = new ShoppingList();
        $list1->setName('List 1');
        $list1->setOwner($this->owner);
        $list1->setUser($this->owner);

        $list2 = new ShoppingList();
        $list2->setName('List 2');
        $list2->setOwner($this->owner);
        $list2->setUser($this->owner);

        $share1 = new ListShare();
        $share1->setShoppingList($list1);
        $share1->setOwner($this->owner);
        $share1->setSharedWithUser($this->sharedUser);

        $share2 = new ListShare();
        $share2->setShoppingList($list2);
        $share2->setOwner($this->owner);
        $share2->setSharedWithUser($this->sharedUser);

        $expectedShares = [$share1, $share2];

        $this->shareRepository->expects($this->once())
            ->method('findAllByUser')
            ->with($this->sharedUser)
            ->willReturn($expectedShares);

        $result = $this->service->getUserSharedLists($this->sharedUser);

        $this->assertCount(2, $result);
        $this->assertSame($list1, $result[0]);
        $this->assertSame($list2, $result[1]);
    }
}
