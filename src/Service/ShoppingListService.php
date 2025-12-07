<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateShoppingListDto;
use App\Dto\UpdateShoppingListDto;
use App\Entity\ShoppingList;
use App\Entity\User;
use App\Repository\ShoppingListRepository;
use Doctrine\ORM\EntityManagerInterface;

class ShoppingListService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShoppingListRepository $shoppingListRepository
    ) {
    }

    public function createShoppingList(CreateShoppingListDto $dto, User $user): ShoppingList
    {
        $shoppingList = new ShoppingList();
        $shoppingList->setName($dto->name);
        $shoppingList->setDescription($dto->description);
        $shoppingList->setUser($user);

        $this->entityManager->persist($shoppingList);
        $this->entityManager->flush();

        return $shoppingList;
    }

    public function updateShoppingList(ShoppingList $shoppingList, UpdateShoppingListDto $dto): ShoppingList
    {
        if ($dto->name !== null) {
            $shoppingList->setName($dto->name);
        }

        if ($dto->description !== null) {
            $shoppingList->setDescription($dto->description);
        }

        $shoppingList->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $shoppingList;
    }

    public function deleteShoppingList(ShoppingList $shoppingList): void
    {
        $this->entityManager->remove($shoppingList);
        $this->entityManager->flush();
    }

    public function findUserShoppingLists(User $user): array
    {
        return $this->shoppingListRepository->findBy(
            ['user' => $user],
            ['updatedAt' => 'DESC']
        );
    }

    public function findUserShoppingList(int $id, User $user): ?ShoppingList
    {
        return $this->shoppingListRepository->findOneBy([
            'id' => $id,
            'user' => $user
        ]);
    }
}
