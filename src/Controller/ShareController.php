<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ListShareResponseDto;
use App\Dto\ShareListDto;
use App\Entity\User;
use App\Repository\ListShareRepository;
use App\Repository\ShoppingListRepository;
use App\Repository\UserRepository;
use App\Service\ListShareService;
use App\Service\ShoppingListService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/shopping-lists/{id}/shares')]
class ShareController extends AbstractController
{
    public function __construct(
        private ListShareService $shareService,
        private ShoppingListService $listService,
        private ShoppingListRepository $shoppingListRepository,
        private ListShareRepository $shareRepository,
        private UserRepository $userRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $this->logger->info('Fetching list shares', [
            'user_id' => $user->getTelegramId(),
            'list_id' => $id,
        ]);

        // Check if list exists (regardless of access)
        $list = $this->shoppingListRepository->find($id);
        if (!$list) {
            $this->logger->warning('Shopping list not found for shares', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
            ]);

            return $this->json([
                'error' => 'Shopping list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Must have access (owner or collaborator)
        if (!$this->listService->canUserAccessList($list, $user)) {
            $this->logger->warning('Access denied to list shares', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
            ]);

            return $this->json([
                'error' => 'Access denied',
            ], Response::HTTP_FORBIDDEN);
        }

        $shares = $this->shareService->getListShares($list);
        $shareDtos = array_map(fn ($share) => ListShareResponseDto::fromEntity($share), $shares);

        $this->logger->debug('List shares retrieved', [
            'user_id' => $user->getTelegramId(),
            'list_id' => $id,
            'count' => count($shareDtos),
        ]);

        return $this->json($shareDtos, Response::HTTP_OK);
    }

    #[Route('', methods: ['POST'])]
    public function create(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->logger->info('Creating share', [
            'user_id' => $user->getTelegramId(),
            'list_id' => $id,
        ]);

        // Check if list exists (regardless of access)
        $list = $this->shoppingListRepository->find($id);
        if (!$list) {
            $this->logger->warning('Shopping list not found for sharing', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
            ]);

            return $this->json([
                'error' => 'Shopping list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Only owner can share
        if (!$this->listService->isUserOwner($list, $user)) {
            $this->logger->warning('Non-owner attempted to share list', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
            ]);

            return $this->json([
                'error' => 'Only the owner can share this list',
            ], Response::HTTP_FORBIDDEN);
        }

        $dto = $this->serializer->deserialize(
            $request->getContent(),
            ShareListDto::class,
            'json'
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $this->logger->warning('Share creation validation failed', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
                'errors' => (string) $errors,
            ]);

            return $this->json([
                'error' => 'Validation failed',
                'details' => (string) $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $share = $this->shareService->shareList($list, $dto->telegramUsername, $user);
            $responseDto = ListShareResponseDto::fromEntity($share);

            $this->logger->info('Share created successfully', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
                'share_id' => $share->getId(),
                'shared_with_user_id' => $share->getSharedWithUser()->getTelegramId(),
            ]);

            return $this->json($responseDto, Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            $this->logger->warning('Share creation failed: user not found', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        } catch (\LogicException $e) {
            $this->logger->warning('Share creation failed: logic error', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'error' => $e->getMessage(),
            ], Response::HTTP_CONFLICT);
        }
    }

    #[Route('/{userId}', methods: ['DELETE'])]
    public function delete(int $id, int $userId, #[CurrentUser] User $user): JsonResponse
    {
        $this->logger->info('Removing share', [
            'user_id' => $user->getTelegramId(),
            'list_id' => $id,
            'shared_user_id' => $userId,
        ]);

        // Check if list exists (regardless of access)
        $list = $this->shoppingListRepository->find($id);
        if (!$list) {
            $this->logger->warning('Shopping list not found for share removal', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
            ]);

            return $this->json([
                'error' => 'Shopping list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Find share
        $sharedUser = $this->userRepository->find($userId);
        if (!$sharedUser) {
            $this->logger->warning('User not found for share removal', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
                'shared_user_id' => $userId,
            ]);

            return $this->json([
                'error' => 'User not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $share = $this->shareRepository->findByListAndUser($list, $sharedUser);
        if (!$share) {
            $this->logger->warning('Share not found', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
                'shared_user_id' => $userId,
            ]);

            return $this->json([
                'error' => 'Share not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Authorization: owner can remove anyone, collaborator can leave (remove self)
        $isOwner = $this->listService->isUserOwner($list, $user);
        $isRemovingSelf = $userId === $user->getId();

        if (!$isOwner && !$isRemovingSelf) {
            $this->logger->warning('Unauthorized share removal attempt', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $id,
                'shared_user_id' => $userId,
            ]);

            return $this->json([
                'error' => 'Access denied',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->shareService->removeShare($share);

        $this->logger->info('Share removed successfully', [
            'user_id' => $user->getTelegramId(),
            'list_id' => $id,
            'shared_user_id' => $userId,
        ]);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
