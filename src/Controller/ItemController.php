<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateItemDto;
use App\Dto\UpdateItemDto;
use App\Entity\ShoppingList;
use App\Service\ItemService;
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
        private ValidatorInterface $validator
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(ShoppingList $shoppingList): JsonResponse
    {
        $items = $this->itemService->findShoppingListItems($shoppingList);

        return $this->json($items, Response::HTTP_OK, [], [
            'groups' => ['item:read']
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(ShoppingList $shoppingList, Request $request): JsonResponse
    {
        $dto = $this->serializer->deserialize(
            $request->getContent(),
            CreateItemDto::class,
            'json'
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json([
                'error' => 'Validation failed',
                'details' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        $item = $this->itemService->createItem($dto, $shoppingList);

        return $this->json($item, Response::HTTP_CREATED, [], [
            'groups' => ['item:read']
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(ShoppingList $shoppingList, int $id): JsonResponse
    {
        $item = $this->itemService->findItem($id, $shoppingList);

        if (!$item) {
            return $this->json([
                'error' => 'Item not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($item, Response::HTTP_OK, [], [
            'groups' => ['item:read']
        ]);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(ShoppingList $shoppingList, int $id, Request $request): JsonResponse
    {
        $item = $this->itemService->findItem($id, $shoppingList);

        if (!$item) {
            return $this->json([
                'error' => 'Item not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->serializer->deserialize(
            $request->getContent(),
            UpdateItemDto::class,
            'json'
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json([
                'error' => 'Validation failed',
                'details' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        $item = $this->itemService->updateItem($item, $dto);

        return $this->json($item, Response::HTTP_OK, [], [
            'groups' => ['item:read']
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(ShoppingList $shoppingList, int $id): JsonResponse
    {
        $item = $this->itemService->findItem($id, $shoppingList);

        if (!$item) {
            return $this->json([
                'error' => 'Item not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $this->itemService->deleteItem($item);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/toggle', methods: ['POST'])]
    public function toggle(ShoppingList $shoppingList, int $id): JsonResponse
    {
        $item = $this->itemService->findItem($id, $shoppingList);

        if (!$item) {
            return $this->json([
                'error' => 'Item not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $item = $this->itemService->toggleItem($item);

        return $this->json($item, Response::HTTP_OK, [], [
            'groups' => ['item:read']
        ]);
    }
}
