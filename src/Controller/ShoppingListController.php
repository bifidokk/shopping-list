<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateShoppingListDto;
use App\Dto\ShoppingListDto;
use App\Dto\UpdateShoppingListDto;
use App\Entity\User;
use App\Repository\ItemRepository;
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

#[Route('/api/shopping-lists')]
class ShoppingListController extends AbstractController
{
    public function __construct(
        private ShoppingListService $shoppingListService,
        private ItemRepository $itemRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        $this->logger->info('Fetching shopping lists', [
            'user_id' => $user->getTelegramId(),
        ]);

        $lists = $this->shoppingListService->findUserShoppingLists($user);

        $itemCounts = $this->itemRepository->getItemCountsForLists($lists);

        // Get user's default list ID for computing isDefault per user
        $userDefaultListId = $this->shoppingListService->getUserDefaultListId($user);

        $listDtos = array_map(function ($list) use ($itemCounts, $user, $userDefaultListId) {
            $listId = $list->getId();
            $counts = $itemCounts[$listId] ?? ['total' => 0, 'completed' => 0];

            return ShoppingListDto::fromEntity($list, $counts['total'], $counts['completed'], $user, $userDefaultListId);
        }, $lists);

        $this->logger->debug('Shopping lists retrieved', [
            'user_id' => $user->getTelegramId(),
            'count' => count($listDtos),
        ]);

        return $this->json($listDtos, Response::HTTP_OK);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->logger->info('Creating shopping list', [
            'user_id' => $user->getTelegramId(),
        ]);

        $dto = $this->serializer->deserialize(
            $request->getContent(),
            CreateShoppingListDto::class,
            'json'
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $this->logger->warning('Shopping list creation validation failed', [
                'user_id' => $user->getTelegramId(),
                'errors' => (string) $errors,
            ]);

            return $this->json([
                'error' => 'Validation failed',
                'details' => (string) $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $shoppingList = $this->shoppingListService->createShoppingList($dto, $user);

        $this->logger->info('Shopping list created', [
            'user_id' => $user->getTelegramId(),
            'shopping_list_id' => $shoppingList->getId(),
            'name' => $shoppingList->getName(),
        ]);

        // Get user's default list ID for computing isDefault
        $userDefaultListId = $this->shoppingListService->getUserDefaultListId($user);

        // Get item counts
        $itemCounts = $this->itemRepository->getItemCountsForLists([$shoppingList]);
        $listId = $shoppingList->getId();
        $counts = $itemCounts[$listId] ?? ['total' => 0, 'completed' => 0];

        $responseDto = ShoppingListDto::fromEntity(
            $shoppingList,
            $counts['total'],
            $counts['completed'],
            $user,
            $userDefaultListId
        );

        return $this->json($responseDto, Response::HTTP_CREATED);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $this->logger->info('Fetching shopping list', [
            'user_id' => $user->getTelegramId(),
            'shopping_list_id' => $id,
        ]);

        $shoppingList = $this->shoppingListService->findUserShoppingList($id, $user);

        if (!$shoppingList) {
            $this->logger->warning('Shopping list not found', [
                'user_id' => $user->getTelegramId(),
                'shopping_list_id' => $id,
            ]);

            return $this->json([
                'error' => 'Shopping list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($shoppingList, Response::HTTP_OK, [], [
            'groups' => ['shopping_list:read', 'shopping_list:items'],
        ]);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $this->logger->info('Updating shopping list', [
            'user_id' => $user->getTelegramId(),
            'shopping_list_id' => $id,
        ]);

        $shoppingList = $this->shoppingListService->findUserShoppingList($id, $user);

        if (!$shoppingList) {
            $this->logger->warning('Shopping list not found for update', [
                'user_id' => $user->getTelegramId(),
                'shopping_list_id' => $id,
            ]);

            return $this->json([
                'error' => 'Shopping list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->serializer->deserialize(
            $request->getContent(),
            UpdateShoppingListDto::class,
            'json'
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $this->logger->warning('Shopping list update validation failed', [
                'user_id' => $user->getTelegramId(),
                'shopping_list_id' => $id,
                'errors' => (string) $errors,
            ]);

            return $this->json([
                'error' => 'Validation failed',
                'details' => (string) $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $shoppingList = $this->shoppingListService->updateShoppingList($shoppingList, $dto);

        $this->logger->info('Shopping list updated', [
            'user_id' => $user->getTelegramId(),
            'shopping_list_id' => $id,
        ]);

        // Get user's default list ID for computing isDefault
        $userDefaultListId = $this->shoppingListService->getUserDefaultListId($user);

        // Get item counts
        $itemCounts = $this->itemRepository->getItemCountsForLists([$shoppingList]);
        $listId = $shoppingList->getId();
        $counts = $itemCounts[$listId] ?? ['total' => 0, 'completed' => 0];

        $responseDto = ShoppingListDto::fromEntity(
            $shoppingList,
            $counts['total'],
            $counts['completed'],
            $user,
            $userDefaultListId
        );

        return $this->json($responseDto, Response::HTTP_OK);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $this->logger->info('Deleting shopping list', [
            'user_id' => $user->getTelegramId(),
            'shopping_list_id' => $id,
        ]);

        $shoppingList = $this->shoppingListService->findUserShoppingList($id, $user);

        if (!$shoppingList) {
            $this->logger->warning('Shopping list not found for deletion', [
                'user_id' => $user->getTelegramId(),
                'shopping_list_id' => $id,
            ]);

            return $this->json([
                'error' => 'Shopping list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Only owner can delete
        if (!$this->shoppingListService->isUserOwner($shoppingList, $user)) {
            $this->logger->warning('Non-owner attempted to delete list', [
                'user_id' => $user->getTelegramId(),
                'shopping_list_id' => $id,
            ]);

            return $this->json([
                'error' => 'Only the owner can delete this list',
            ], Response::HTTP_FORBIDDEN);
        }

        $this->shoppingListService->deleteShoppingList($shoppingList);

        $this->logger->info('Shopping list deleted', [
            'user_id' => $user->getTelegramId(),
            'shopping_list_id' => $id,
        ]);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/set-default', methods: ['POST'])]
    public function setDefault(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $this->logger->info('Setting shopping list as default', [
            'user_id' => $user->getTelegramId(),
            'shopping_list_id' => $id,
        ]);

        $shoppingList = $this->shoppingListService->findUserShoppingList($id, $user);

        if (!$shoppingList) {
            $this->logger->warning('Shopping list not found for set default', [
                'user_id' => $user->getTelegramId(),
                'shopping_list_id' => $id,
            ]);

            return $this->json([
                'error' => 'Shopping list not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if already default for this user
        $userDefaultListId = $this->shoppingListService->getUserDefaultListId($user);
        if ($userDefaultListId === $id) {
            $this->logger->debug('Shopping list is already default for this user', [
                'user_id' => $user->getTelegramId(),
                'shopping_list_id' => $id,
            ]);

            // Get item counts and return DTO
            $itemCounts = $this->itemRepository->getItemCountsForLists([$shoppingList]);
            $listId = $shoppingList->getId();
            $counts = $itemCounts[$listId] ?? ['total' => 0, 'completed' => 0];

            $responseDto = ShoppingListDto::fromEntity(
                $shoppingList,
                $counts['total'],
                $counts['completed'],
                $user,
                $userDefaultListId
            );

            return $this->json($responseDto, Response::HTTP_OK);
        }

        // Set as default for the current user (owner OR collaborator can set their own default)
        $shoppingList = $this->shoppingListService->setAsDefault($shoppingList, $user);

        $this->logger->info('Shopping list set as default for user', [
            'user_id' => $user->getTelegramId(),
            'shopping_list_id' => $id,
        ]);

        // Get updated default ID and item counts
        $userDefaultListId = $this->shoppingListService->getUserDefaultListId($user);
        $itemCounts = $this->itemRepository->getItemCountsForLists([$shoppingList]);
        $listId = $shoppingList->getId();
        $counts = $itemCounts[$listId] ?? ['total' => 0, 'completed' => 0];

        $responseDto = ShoppingListDto::fromEntity(
            $shoppingList,
            $counts['total'],
            $counts['completed'],
            $user,
            $userDefaultListId
        );

        return $this->json($responseDto, Response::HTTP_OK);
    }
}
