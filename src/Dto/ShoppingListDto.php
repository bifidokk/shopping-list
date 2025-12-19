<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\ShoppingList;

class ShoppingListDto
{
    public int $id;
    public string $name;
    public ?string $description;
    public bool $isDefault;
    public \DateTimeInterface $createdAt;
    public \DateTimeInterface $updatedAt;
    public int $totalItems;
    public int $completedItems;

    public static function fromEntity(ShoppingList $shoppingList, int $totalItems = 0, int $completedItems = 0): self
    {
        $id = $shoppingList->getId();
        if ($id === null) {
            throw new \LogicException('Cannot create DTO from unpersisted entity');
        }

        $dto = new self();
        $dto->id = $id;
        $dto->name = $shoppingList->getName();
        $dto->description = $shoppingList->getDescription();
        $dto->isDefault = $shoppingList->isDefault();
        $dto->createdAt = $shoppingList->getCreatedAt();
        $dto->updatedAt = $shoppingList->getUpdatedAt();
        $dto->totalItems = $totalItems;
        $dto->completedItems = $completedItems;

        return $dto;
    }
}
