<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\CreateItemDto;
use App\Dto\UpdateItemDto;
use App\Entity\Item;
use App\Entity\ShoppingList;
use App\Entity\User;
use App\Repository\ItemRepository;
use App\Service\ItemService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ItemServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private ItemRepository $repository;
    private ItemService $service;
    private ShoppingList $shoppingList;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->repository = $this->createMock(ItemRepository::class);
        $this->service = new ItemService($this->entityManager, $this->repository);

        $user = new User();
        $user->setTelegramId(123456789);

        $this->shoppingList = new ShoppingList();
        $this->shoppingList->setName('Test List');
        $this->shoppingList->setUser($user);
    }

    public function testCreateItem(): void
    {
        $dto = new CreateItemDto();
        $dto->name = 'Test Item';
        $dto->quantity = 2;
        $dto->unit = 'kg';
        $dto->notes = 'Test notes';
        $dto->isDone = false;

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Item $item) {
                return $item->getName() === 'Test Item'
                    && $item->getQuantity() === 2
                    && $item->getUnit() === 'kg'
                    && $item->getNotes() === 'Test notes'
                    && $item->isDone() === false
                    && $item->getShoppingList() === $this->shoppingList;
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->createItem($dto, $this->shoppingList);

        $this->assertInstanceOf(Item::class, $result);
        $this->assertSame('Test Item', $result->getName());
        $this->assertSame(2, $result->getQuantity());
        $this->assertSame('kg', $result->getUnit());
        $this->assertSame('Test notes', $result->getNotes());
        $this->assertFalse($result->isDone());
    }

    public function testCreateItemMinimal(): void
    {
        $dto = new CreateItemDto();
        $dto->name = 'Test Item';

        $this->entityManager->expects($this->once())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->createItem($dto, $this->shoppingList);

        $this->assertSame('Test Item', $result->getName());
        $this->assertNull($result->getQuantity());
        $this->assertNull($result->getUnit());
        $this->assertNull($result->getNotes());
        $this->assertFalse($result->isDone());
    }

    public function testUpdateItem(): void
    {
        $item = new Item();
        $item->setName('Old Name');
        $item->setQuantity(1);
        $item->setUnit('pcs');
        $item->setNotes('Old notes');
        $item->setIsDone(false);
        $item->setShoppingList($this->shoppingList);

        $dto = new UpdateItemDto();
        $dto->name = 'New Name';
        $dto->quantity = 5;
        $dto->unit = 'kg';
        $dto->notes = 'New notes';
        $dto->isDone = true;

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->updateItem($item, $dto);

        $this->assertSame('New Name', $result->getName());
        $this->assertSame(5, $result->getQuantity());
        $this->assertSame('kg', $result->getUnit());
        $this->assertSame('New notes', $result->getNotes());
        $this->assertTrue($result->isDone());
    }

    public function testUpdateItemPartial(): void
    {
        $item = new Item();
        $item->setName('Old Name');
        $item->setQuantity(1);
        $item->setUnit('pcs');
        $item->setNotes('Old notes');
        $item->setIsDone(false);
        $item->setShoppingList($this->shoppingList);

        $dto = new UpdateItemDto();
        $dto->name = 'New Name';
        // other fields are null (not updated)

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->updateItem($item, $dto);

        $this->assertSame('New Name', $result->getName());
        $this->assertSame(1, $result->getQuantity());
        $this->assertSame('pcs', $result->getUnit());
        $this->assertSame('Old notes', $result->getNotes());
        $this->assertFalse($result->isDone());
    }

    public function testDeleteItem(): void
    {
        $item = new Item();
        $item->setName('Test Item');
        $item->setShoppingList($this->shoppingList);

        $this->entityManager->expects($this->once())
            ->method('remove')
            ->with($item);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->service->deleteItem($item);
    }

    public function testToggleItem(): void
    {
        $item = new Item();
        $item->setName('Test Item');
        $item->setIsDone(false);
        $item->setShoppingList($this->shoppingList);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->toggleItem($item);

        $this->assertTrue($result->isDone());
    }

    public function testToggleItemFromTrueToFalse(): void
    {
        $item = new Item();
        $item->setName('Test Item');
        $item->setIsDone(true);
        $item->setShoppingList($this->shoppingList);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->toggleItem($item);

        $this->assertFalse($result->isDone());
    }

    public function testFindShoppingListItems(): void
    {
        $item1 = new Item();
        $item1->setName('Item 1');
        $item1->setShoppingList($this->shoppingList);

        $item2 = new Item();
        $item2->setName('Item 2');
        $item2->setShoppingList($this->shoppingList);

        $expectedItems = [$item1, $item2];

        $this->repository->expects($this->once())
            ->method('findBy')
            ->with(
                ['shoppingList' => $this->shoppingList],
                ['createdAt' => 'ASC']
            )
            ->willReturn($expectedItems);

        $result = $this->service->findShoppingListItems($this->shoppingList);

        $this->assertSame($expectedItems, $result);
    }

    public function testFindItem(): void
    {
        $item = new Item();
        $item->setName('Test Item');
        $item->setShoppingList($this->shoppingList);

        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'id' => 1,
                'shoppingList' => $this->shoppingList
            ])
            ->willReturn($item);

        $result = $this->service->findItem(1, $this->shoppingList);

        $this->assertSame($item, $result);
    }

    public function testFindItemNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with([
                'id' => 999,
                'shoppingList' => $this->shoppingList
            ])
            ->willReturn(null);

        $result = $this->service->findItem(999, $this->shoppingList);

        $this->assertNull($result);
    }
}
