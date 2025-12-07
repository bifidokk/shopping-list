<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\CreateShoppingListDto;
use App\Dto\UpdateShoppingListDto;
use App\Entity\User;
use App\Service\ShoppingListService;
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
        private SerializerInterface $serializer,
        private ValidatorInterface $validator
    ) {
    }

    #[Route('', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): JsonResponse
    {
        $lists = $this->shoppingListService->findUserShoppingLists($user);

        return $this->json($lists, Response::HTTP_OK, [], [
            'groups' => ['shopping_list:read']
        ]);
    }

    #[Route('', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $dto = $this->serializer->deserialize(
            $request->getContent(),
            CreateShoppingListDto::class,
            'json'
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json([
                'error' => 'Validation failed',
                'details' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        $shoppingList = $this->shoppingListService->createShoppingList($dto, $user);

        return $this->json($shoppingList, Response::HTTP_CREATED, [], [
            'groups' => ['shopping_list:read']
        ]);
    }

    #[Route('/{id}', methods: ['GET'])]
    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $shoppingList = $this->shoppingListService->findUserShoppingList($id, $user);

        if (!$shoppingList) {
            return $this->json([
                'error' => 'Shopping list not found'
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($shoppingList, Response::HTTP_OK, [], [
            'groups' => ['shopping_list:read', 'shopping_list:items']
        ]);
    }

    #[Route('/{id}', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $shoppingList = $this->shoppingListService->findUserShoppingList($id, $user);

        if (!$shoppingList) {
            return $this->json([
                'error' => 'Shopping list not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $dto = $this->serializer->deserialize(
            $request->getContent(),
            UpdateShoppingListDto::class,
            'json'
        );

        $errors = $this->validator->validate($dto);
        if (count($errors) > 0) {
            return $this->json([
                'error' => 'Validation failed',
                'details' => (string) $errors
            ], Response::HTTP_BAD_REQUEST);
        }

        $shoppingList = $this->shoppingListService->updateShoppingList($shoppingList, $dto);

        return $this->json($shoppingList, Response::HTTP_OK, [], [
            'groups' => ['shopping_list:read']
        ]);
    }

    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $shoppingList = $this->shoppingListService->findUserShoppingList($id, $user);

        if (!$shoppingList) {
            return $this->json([
                'error' => 'Shopping list not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $this->shoppingListService->deleteShoppingList($shoppingList);

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
