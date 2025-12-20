<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateItemDto;
use App\Dto\UpdateItemDto;
use App\Entity\Item;
use App\Entity\ShoppingList;
use App\Repository\ItemRepository;
use Doctrine\ORM\EntityManagerInterface;

class ItemService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ItemRepository $itemRepository
    ) {
    }

    public function createItem(CreateItemDto $dto, ShoppingList $shoppingList): Item
    {
        $item = new Item();
        $item->setName($dto->name);
        $item->setQuantity($dto->quantity);
        $item->setUnit($dto->unit);
        $item->setNotes($dto->notes);
        $item->setIsDone($dto->isDone);
        $item->setShoppingList($shoppingList);

        $this->entityManager->persist($item);
        $shoppingList->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $item;
    }

    public function updateItem(Item $item, UpdateItemDto $dto): Item
    {
        if ($dto->name !== null) {
            $item->setName($dto->name);
        }

        if ($dto->quantity !== null) {
            $item->setQuantity($dto->quantity);
        }

        if ($dto->unit !== null) {
            $item->setUnit($dto->unit);
        }

        if ($dto->notes !== null) {
            $item->setNotes($dto->notes);
        }

        if ($dto->isDone !== null) {
            $item->setIsDone($dto->isDone);
        }

        $item->setUpdatedAt(new \DateTime());
        $item->getShoppingList()->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $item;
    }

    public function deleteItem(Item $item): void
    {
        $shoppingList = $item->getShoppingList();
        $this->entityManager->remove($item);
        $shoppingList->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();
    }

    public function toggleItem(Item $item): Item
    {
        $item->setIsDone(!$item->isDone());
        $item->setUpdatedAt(new \DateTime());
        $item->getShoppingList()->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $item;
    }

    /**
     * @return Item[]
     */
    public function findShoppingListItems(ShoppingList $shoppingList): array
    {
        return $this->itemRepository->findBy(
            ['shoppingList' => $shoppingList],
            ['createdAt' => 'ASC']
        );
    }

    public function findItem(int $id, ShoppingList $shoppingList): ?Item
    {
        return $this->itemRepository->findOneBy([
            'id' => $id,
            'shoppingList' => $shoppingList,
        ]);
    }

    public function addItemIfNotExists(string $itemName, ShoppingList $shoppingList): ?Item
    {
        $existingItem = $this->itemRepository->findOneByNameAndList($itemName, $shoppingList);

        if ($existingItem) {
            return null;
        }

        $item = new Item();
        $item->setName($itemName);
        $item->setQuantity(1);
        $item->setUnit(null);
        $item->setNotes(null);
        $item->setIsDone(false);
        $item->setShoppingList($shoppingList);

        $this->entityManager->persist($item);
        $shoppingList->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $item;
    }
}
