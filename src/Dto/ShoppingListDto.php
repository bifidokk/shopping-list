<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\ShoppingList;
use App\Entity\User;

class ShoppingListDto
{
    public int $id;
    public string $name;
    public ?string $description;
    public bool $isDefault;
    public int $ownerId;
    public bool $isOwner;
    public int $sharedWith;
    public \DateTimeInterface $createdAt;
    public \DateTimeInterface $updatedAt;
    public int $totalItems;
    public int $completedItems;

    public static function fromEntity(
        ShoppingList $shoppingList,
        int $totalItems = 0,
        int $completedItems = 0,
        ?User $currentUser = null,
        ?int $userDefaultListId = null
    ): self {
        $id = $shoppingList->getId();
        if ($id === null) {
            throw new \LogicException('Cannot create DTO from unpersisted entity');
        }

        $ownerId = $shoppingList->getOwner()->getId();
        if ($ownerId === null) {
            throw new \LogicException('Cannot create DTO from unpersisted owner');
        }

        $dto = new self();
        $dto->id = $id;
        $dto->name = $shoppingList->getName();
        $dto->description = $shoppingList->getDescription();
        // Compute isDefault based on whether this list is the user's default (per-user setting)
        $dto->isDefault = $userDefaultListId !== null && $id === $userDefaultListId;
        $dto->ownerId = $ownerId;
        $dto->isOwner = $currentUser ? $ownerId === $currentUser->getId() : false;
        $dto->sharedWith = $shoppingList->getSharedWith();
        $dto->createdAt = $shoppingList->getCreatedAt();
        $dto->updatedAt = $shoppingList->getUpdatedAt();
        $dto->totalItems = $totalItems;
        $dto->completedItems = $completedItems;

        return $dto;
    }
}
