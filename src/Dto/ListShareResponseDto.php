<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\ListShare;

class ListShareResponseDto
{
    public int $id;
    public int $listId;
    public int $ownerId;
    public int $sharedWithUserId;
    public ?string $sharedWithUsername;
    public ?string $sharedWithFirstName;
    public ?string $sharedWithLastName;
    public \DateTimeInterface $createdAt;

    public static function fromEntity(ListShare $share): self
    {
        $id = $share->getId();
        if ($id === null) {
            throw new \LogicException('Cannot create DTO from unpersisted entity');
        }

        $listId = $share->getShoppingList()->getId();
        if ($listId === null) {
            throw new \LogicException('Cannot create DTO from unpersisted shopping list');
        }

        $ownerId = $share->getOwner()->getId();
        if ($ownerId === null) {
            throw new \LogicException('Cannot create DTO from unpersisted owner');
        }

        $sharedWithUserId = $share->getSharedWithUser()->getId();
        if ($sharedWithUserId === null) {
            throw new \LogicException('Cannot create DTO from unpersisted user');
        }

        $dto = new self();
        $dto->id = $id;
        $dto->listId = $listId;
        $dto->ownerId = $ownerId;
        $dto->sharedWithUserId = $sharedWithUserId;
        $dto->sharedWithUsername = $share->getSharedWithUser()->getUsername();
        $dto->sharedWithFirstName = $share->getSharedWithUser()->getFirstName();
        $dto->sharedWithLastName = $share->getSharedWithUser()->getLastName();
        $dto->createdAt = $share->getCreatedAt();

        return $dto;
    }
}
