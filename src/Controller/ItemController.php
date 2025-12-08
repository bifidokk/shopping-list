<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateItemDto;
use App\Dto\UpdateItemDto;
use App\Entity\ShoppingList;
use App\Service\ItemService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/shopping-lists/{listId}/items')]
class ItemController extends AbstractController
{
    public function __construct(
        private ItemService $itemService,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(ShoppingList $shoppingList): JsonResponse
    {
        $this->logger->info('Fetching items', [
            'shopping_list_id' => $shoppingList->getId(),
        ]);

        $items = $this->itemService->findShoppingListItems($shoppingList);

        $this->logger->debug('Items retrieved', [
            'shopping_list_id' => $shoppingList->getId(),
            'count' => count($items),
        ]);

        return $this->json($items, Response::HTTP_OK, [], [
            'groups' => ['item:read'],
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(ShoppingList $shoppingList, Request $request): JsonResponse
    {
        $this->logger->info('Creating item', [
            'shopping_list_id' => $shoppingList->getId(),
        ]);

        $dto = $this->serializer->deserialize(
            $request->getContent(),
            CreateItemDto::class,
            'json'
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $this->logger->warning('Item creation validation failed', [
                'shopping_list_id' => $shoppingList->getId(),
                'errors' => (string) $errors,
            ]);

            return $this->json([
                'error' => 'Validation failed',
                'details' => (string) $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $item = $this->itemService->createItem($dto, $shoppingList);

        $this->logger->info('Item created', [
            'shopping_list_id' => $shoppingList->getId(),
            'item_id' => $item->getId(),
            'name' => $item->getName(),
        ]);

        return $this->json($item, Response::HTTP_CREATED, [], [
            'groups' => ['item:read'],
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(ShoppingList $shoppingList, int $id): JsonResponse
    {
        $item = $this->itemService->findItem($id, $shoppingList);

        if (!$item) {
            $this->logger->warning('Item not found', [
                'shopping_list_id' => $shoppingList->getId(),
                'item_id' => $id,
            ]);

            return $this->json([
                'error' => 'Item not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($item, Response::HTTP_OK, [], [
            'groups' => ['item:read'],
        ]);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(ShoppingList $shoppingList, int $id, Request $request): JsonResponse
    {
        $this->logger->info('Updating item', [
            'shopping_list_id' => $shoppingList->getId(),
            'item_id' => $id,
        ]);

        $item = $this->itemService->findItem($id, $shoppingList);

        if (!$item) {
            $this->logger->warning('Item not found for update', [
                'shopping_list_id' => $shoppingList->getId(),
                'item_id' => $id,
            ]);

            return $this->json([
                'error' => 'Item not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->serializer->deserialize(
            $request->getContent(),
            UpdateItemDto::class,
            'json'
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            $this->logger->warning('Item update validation failed', [
                'shopping_list_id' => $shoppingList->getId(),
                'item_id' => $id,
                'errors' => (string) $errors,
            ]);

            return $this->json([
                'error' => 'Validation failed',
                'details' => (string) $errors,
            ], Response::HTTP_BAD_REQUEST);
        }

        $item = $this->itemService->updateItem($item, $dto);

        $this->logger->info('Item updated', [
            'shopping_list_id' => $shoppingList->getId(),
            'item_id' => $id,
        ]);

        return $this->json($item, Response::HTTP_OK, [], [
            'groups' => ['item:read'],
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(ShoppingList $shoppingList, int $id): JsonResponse
    {
        $this->logger->info('Deleting item', [
            'shopping_list_id' => $shoppingList->getId(),
            'item_id' => $id,
        ]);

        $item = $this->itemService->findItem($id, $shoppingList);

        if (!$item) {
            $this->logger->warning('Item not found for deletion', [
                'shopping_list_id' => $shoppingList->getId(),
                'item_id' => $id,
            ]);

            return $this->json([
                'error' => 'Item not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $this->itemService->deleteItem($item);

        $this->logger->info('Item deleted', [
            'shopping_list_id' => $shoppingList->getId(),
            'item_id' => $id,
        ]);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/toggle', methods: ['POST'])]
    public function toggle(ShoppingList $shoppingList, int $id): JsonResponse
    {
        $this->logger->info('Toggling item status', [
            'shopping_list_id' => $shoppingList->getId(),
            'item_id' => $id,
        ]);

        $item = $this->itemService->findItem($id, $shoppingList);

        if (!$item) {
            $this->logger->warning('Item not found for toggle', [
                'shopping_list_id' => $shoppingList->getId(),
                'item_id' => $id,
            ]);

            return $this->json([
                'error' => 'Item not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $item = $this->itemService->toggleItem($item);

        $this->logger->info('Item toggled', [
            'shopping_list_id' => $shoppingList->getId(),
            'item_id' => $id,
            'is_done' => $item->isDone(),
        ]);

        return $this->json($item, Response::HTTP_OK, [], [
            'groups' => ['item:read'],
        ]);
    }
}
