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

        // Check if this is the user's first list
        $isFirstList = $this->shoppingListRepository->countUserLists($user) === 0;
        $shoppingList->setIsDefault($isFirstList);

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
        $wasDefault = $shoppingList->isDefault();
        $user = $shoppingList->getUser();

        $this->entityManager->remove($shoppingList);
        $this->entityManager->flush();

        // If we deleted the default list, promote another list
        if ($wasDefault) {
            $this->promoteNextListToDefault($user);
        }
    }

    /**
     * @return ShoppingList[]
     */
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
            'user' => $user,
        ]);
    }

    public function setAsDefault(ShoppingList $shoppingList): ShoppingList
    {
        $user = $shoppingList->getUser();

        // Unset current default list FIRST to avoid unique constraint violation
        $currentDefault = $this->shoppingListRepository->findUserDefaultList($user);
        if ($currentDefault && $currentDefault->getId() !== $shoppingList->getId()) {
            $currentDefault->setIsDefault(false);
            $currentDefault->setUpdatedAt(new \DateTime());
            $this->entityManager->flush(); // Flush to remove old default before setting new one
        }

        // Set new default
        $shoppingList->setIsDefault(true);
        $shoppingList->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $shoppingList;
    }

    private function promoteNextListToDefault(User $user): void
    {
        // Find the first remaining list (oldest created)
        $nextList = $this->shoppingListRepository->findFirstNonDefaultList($user);

        if ($nextList) {
            $nextList->setIsDefault(true);
            $nextList->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();
        }
    }
}
