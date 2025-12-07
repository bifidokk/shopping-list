<?php

declare(strict_types=1);

namespace App\Resolver;

use App\Entity\ShoppingList;
use App\Entity\User;
use App\Repository\ShoppingListRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ShoppingListValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ShoppingListRepository $shoppingListRepository,
        private TokenStorageInterface $tokenStorage
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        // Check if the argument type is ShoppingList
        if ($argument->getType() !== ShoppingList::class) {
            return [];
        }

        // Get listId from route parameters
        $listId = $request->attributes->get('listId');
        if (!$listId) {
            return [];
        }

        // Get current user
        $token = $this->tokenStorage->getToken();
        if (!$token) {
            throw new NotFoundHttpException('Shopping list not found');
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            throw new NotFoundHttpException('Shopping list not found');
        }

        // Find shopping list
        $shoppingList = $this->shoppingListRepository->findOneBy([
            'id' => (int) $listId,
            'user' => $user
        ]);

        if (!$shoppingList) {
            throw new NotFoundHttpException('Shopping list not found');
        }

        yield $shoppingList;
    }
}
