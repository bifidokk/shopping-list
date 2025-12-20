<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ListShare;
use App\Entity\ShoppingList;
use App\Entity\User;
use App\Repository\ListShareRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ListShareService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ListShareRepository $shareRepository,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Share a list with a user by Telegram username.
     *
     * @throws \InvalidArgumentException if username not found or invalid
     * @throws \LogicException           if trying to share with self or already shared
     */
    public function shareList(
        ShoppingList $list,
        string $telegramUsername,
        User $owner
    ): ListShare {
        // Normalize username (remove @ if present)
        $username = ltrim($telegramUsername, '@');

        // Find user by username
        $sharedWithUser = $this->userRepository->findOneBy(['username' => $username]);
        if (!$sharedWithUser) {
            throw new \InvalidArgumentException("User with username '@{$username}' not found");
        }

        // Cannot share with self
        if ($sharedWithUser->getId() === $owner->getId()) {
            throw new \LogicException('Cannot share list with yourself');
        }

        // Check if already shared
        $existing = $this->shareRepository->findByListAndUser($list, $sharedWithUser);
        if ($existing) {
            throw new \LogicException('List is already shared with this user');
        }

        // Create share
        $share = new ListShare();
        $share->setShoppingList($list);
        $share->setOwner($owner);
        $share->setSharedWithUser($sharedWithUser);

        $this->entityManager->persist($share);

        // Increment shared count
        $list->setSharedWith($list->getSharedWith() + 1);
        $list->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $this->logger->info('List shared with user', [
            'list_id' => $list->getId(),
            'owner_id' => $owner->getTelegramId(),
            'shared_with_user_id' => $sharedWithUser->getTelegramId(),
        ]);

        return $share;
    }

    public function removeShare(ListShare $share): void
    {
        $list = $share->getShoppingList();

        $this->entityManager->remove($share);

        // Decrement shared count
        $list->setSharedWith(max(0, $list->getSharedWith() - 1));
        $list->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $this->logger->info('Share removed', [
            'share_id' => $share->getId(),
            'list_id' => $list->getId(),
        ]);
    }

    /**
     * @return ListShare[]
     */
    public function getListShares(ShoppingList $list): array
    {
        return $this->shareRepository->findAllByList($list);
    }

    /**
     * @return ShoppingList[]
     */
    public function getUserSharedLists(User $user): array
    {
        $shares = $this->shareRepository->findAllByUser($user);

        return array_map(fn ($share) => $share->getShoppingList(), $shares);
    }
}
