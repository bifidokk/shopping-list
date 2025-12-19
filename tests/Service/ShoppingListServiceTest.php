<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CreateShoppingListDto;
use App\Dto\UpdateShoppingListDto;
use App\Entity\ShoppingList;
use App\Entity\User;
use App\Repository\ShoppingListRepository;
use App\Service\ShoppingListService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ShoppingListServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ShoppingListRepository $repository;
    private ShoppingListService $service;
    private User $user;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(ShoppingListRepository::class);
        $this->service = new ShoppingListService($this->entityManager, $this->repository);

        $this->user = new User();
        $this->user->setTelegramId(123456789);
    }

    public function testCreateShoppingList(): void
    {
        $dto = new CreateShoppingListDto();
        $dto->name = 'Test List';
        $dto->description = 'Test Description';

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (ShoppingList $list) {
                return $list->getName() === 'Test List'
                    && $list->getDescription() === 'Test Description'
                    && $list->getUser() === $this->user;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->createShoppingList($dto, $this->user);

        $this->assertInstanceOf(ShoppingList::class, $result);
        $this->assertSame('Test List', $result->getName());
        $this->assertSame('Test Description', $result->getDescription());
        $this->assertSame($this->user, $result->getUser());
    }

    public function testCreateShoppingListWithoutDescription(): void
    {
        $dto = new CreateShoppingListDto();
        $dto->name = 'Test List';
        $dto->description = null;

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->createShoppingList($dto, $this->user);

        $this->assertNull($result->getDescription());
    }

    public function testUpdateShoppingList(): void
    {
        $shoppingList = new ShoppingList();
        $shoppingList->setName('Old Name');
        $shoppingList->setDescription('Old Description');
        $shoppingList->setUser($this->user);

        $dto = new UpdateShoppingListDto();
        $dto->name = 'New Name';
        $dto->description = 'New Description';

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->updateShoppingList($shoppingList, $dto);

        $this->assertSame('New Name', $result->getName());
        $this->assertSame('New Description', $result->getDescription());
    }

    public function testUpdateShoppingListPartial(): void
    {
        $shoppingList = new ShoppingList();
        $shoppingList->setName('Old Name');
        $shoppingList->setDescription('Old Description');
        $shoppingList->setUser($this->user);

        $dto = new UpdateShoppingListDto();
        $dto->name = 'New Name';
        // description is null (not updated)

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->updateShoppingList($shoppingList, $dto);

        $this->assertSame('New Name', $result->getName());
        $this->assertSame('Old Description', $result->getDescription());
    }

    public function testDeleteShoppingList(): void
    {
        $shoppingList = new ShoppingList();
        $shoppingList->setName('Test List');
        $shoppingList->setUser($this->user);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($shoppingList);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->deleteShoppingList($shoppingList);
    }

    public function testFindUserShoppingLists(): void
    {
        $list1 = new ShoppingList();
        $list1->setName('List 1');
        $list1->setUser($this->user);

        $list2 = new ShoppingList();
        $list2->setName('List 2');
        $list2->setUser($this->user);

        $expectedLists = [$list1, $list2];

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(
                ['user' => $this->user],
                ['updatedAt' => 'DESC']
            )
            ->willReturn($expectedLists);

        $result = $this->service->findUserShoppingLists($this->user);

        $this->assertSame($expectedLists, $result);
    }

    public function testFindUserShoppingList(): void
    {
        $shoppingList = new ShoppingList();
        $shoppingList->setName('Test List');
        $shoppingList->setUser($this->user);

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'id' => 1,
                'user' => $this->user,
            ])
            ->willReturn($shoppingList);

        $result = $this->service->findUserShoppingList(1, $this->user);

        $this->assertSame($shoppingList, $result);
    }

    public function testFindUserShoppingListNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'id' => 999,
                'user' => $this->user,
            ])
            ->willReturn(null);

        $result = $this->service->findUserShoppingList(999, $this->user);

        $this->assertNull($result);
    }

    public function testCreateFirstShoppingListIsDefault(): void
    {
        $dto = new CreateShoppingListDto();
        $dto->name = 'First List';

        // Mock repository to return 0 lists (first list scenario)
        $this->repository->expects($this->once())
            ->method('countUserLists')
            ->with($this->user)
            ->willReturn(0);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->createShoppingList($dto, $this->user);

        $this->assertTrue($result->isDefault());
    }

    public function testCreateSecondShoppingListIsNotDefault(): void
    {
        $dto = new CreateShoppingListDto();
        $dto->name = 'Second List';

        // Mock repository to return 1 existing list
        $this->repository->expects($this->once())
            ->method('countUserLists')
            ->with($this->user)
            ->willReturn(1);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $result = $this->service->createShoppingList($dto, $this->user);

        $this->assertFalse($result->isDefault());
    }

    public function testSetAsDefault(): void
    {
        $currentDefault = new ShoppingList();
        $currentDefault->setName('Current Default');
        $currentDefault->setUser($this->user);
        $currentDefault->setIsDefault(true);

        // Use reflection to set ID
        $reflection = new \ReflectionClass($currentDefault);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($currentDefault, 1);

        $newDefault = new ShoppingList();
        $newDefault->setName('New Default');
        $newDefault->setUser($this->user);
        $newDefault->setIsDefault(false);

        // Use reflection to set ID
        $reflection = new \ReflectionClass($newDefault);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($newDefault, 2);

        $this->repository->expects($this->once())
            ->method('findUserDefaultList')
            ->with($this->user)
            ->willReturn($currentDefault);

        $this->entityManager->expects($this->exactly(2))->method('flush');

        $result = $this->service->setAsDefault($newDefault);

        $this->assertTrue($result->isDefault());
        $this->assertFalse($currentDefault->isDefault());
    }

    public function testDeleteDefaultListPromotesNext(): void
    {
        $defaultList = new ShoppingList();
        $defaultList->setName('Default List');
        $defaultList->setUser($this->user);
        $defaultList->setIsDefault(true);

        // Use reflection to set ID
        $reflection = new \ReflectionClass($defaultList);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($defaultList, 1);

        $nextList = new ShoppingList();
        $nextList->setName('Next List');
        $nextList->setUser($this->user);
        $nextList->setIsDefault(false);

        // Use reflection to set ID
        $reflection = new \ReflectionClass($nextList);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($nextList, 2);

        $this->repository->expects($this->once())
            ->method('findFirstNonDefaultList')
            ->with($this->user)
            ->willReturn($nextList);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($defaultList);

        $this->entityManager->expects($this->exactly(2))->method('flush');

        $this->service->deleteShoppingList($defaultList);

        $this->assertTrue($nextList->isDefault());
    }

    public function testDeleteNonDefaultListDoesNotPromote(): void
    {
        $nonDefaultList = new ShoppingList();
        $nonDefaultList->setName('Non-Default List');
        $nonDefaultList->setUser($this->user);
        $nonDefaultList->setIsDefault(false);

        $this->repository->expects($this->never())
            ->method('findFirstNonDefaultList');

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($nonDefaultList);

        $this->entityManager->expects($this->once())->method('flush');

        $this->service->deleteShoppingList($nonDefaultList);
    }
}
