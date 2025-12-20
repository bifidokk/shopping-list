<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateShoppingListDto;
use App\Dto\UpdateShoppingListDto;
use App\Entity\ShoppingList;
use App\Entity\User;
use App\Repository\ShoppingListRepository;
use App\Repository\UserDefaultListRepository;
use Doctrine\ORM\EntityManagerInterface;

class ShoppingListService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShoppingListRepository $shoppingListRepository,
        private UserDefaultListRepository $userDefaultListRepository
    ) {
    }

    public function createShoppingList(CreateShoppingListDto $dto, User $user): ShoppingList
    {
        $shoppingList = new ShoppingList();
        $shoppingList->setName($dto->name);
        $shoppingList->setDescription($dto->description);
        $shoppingList->setUser($user);
        $shoppingList->setOwner($user); // Set owner (initially same as creator)

        // Check if this is the user's first accessible list (owned or shared)
        $hasExistingDefault = $this->userDefaultListRepository->findByUser($user) !== null;
        $shoppingList->setIsDefault(!$hasExistingDefault);

        $this->entityManager->persist($shoppingList);
        $this->entityManager->flush();

        // If this is the user's first list, set it as their default in user_default_lists
        if (!$hasExistingDefault) {
            $this->userDefaultListRepository->setUserDefaultList($user, $shoppingList);
            $this->entityManager->flush();
        }

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
        $listId = $shoppingList->getId();

        $this->entityManager->remove($shoppingList);
        $this->entityManager->flush();

        // Note: CASCADE DELETE will automatically remove user_default_lists entries
        // where shopping_list_id matches this list. Users who had this as default
        // will need to select a new default list manually.
    }

    /**
     * @return ShoppingList[]
     */
    public function findUserShoppingLists(User $user): array
    {
        return $this->shoppingListRepository->findAllAccessibleByUser($user);
    }

    public function findUserShoppingList(int $id, User $user): ?ShoppingList
    {
        // Check if user has access (owner OR collaborator)
        if (!$this->shoppingListRepository->hasAccess($id, $user)) {
            return null;
        }

        return $this->shoppingListRepository->find($id);
    }

    public function setAsDefault(ShoppingList $shoppingList, User $user): ShoppingList
    {
        // Set the user's default list in user_default_lists table
        $this->userDefaultListRepository->setUserDefaultList($user, $shoppingList);
        $shoppingList->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        return $shoppingList;
    }

    public function canUserAccessList(ShoppingList $list, User $user): bool
    {
        $listId = $list->getId();
        if ($listId === null) {
            return false;
        }

        return $this->shoppingListRepository->hasAccess($listId, $user);
    }

    public function isUserOwner(ShoppingList $list, User $user): bool
    {
        $listId = $list->getId();
        if ($listId === null) {
            return false;
        }

        return $this->shoppingListRepository->isOwner($listId, $user);
    }

    public function getUserDefaultListId(User $user): ?int
    {
        return $this->userDefaultListRepository->findDefaultListIdForUser($user);
    }
}
