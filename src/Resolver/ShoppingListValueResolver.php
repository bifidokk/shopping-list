<?php

declare(strict_types=1);

namespace App\Resolver;

use App\Entity\ShoppingList;
use App\Entity\User;
use App\Repository\ShoppingListRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AutoconfigureTag('controller.argument_value_resolver', ['priority' => 150])]
class ShoppingListValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ShoppingListRepository $shoppingListRepository,
        private TokenStorageInterface $tokenStorage
    ) {
    }

    /**
     * @return iterable<ShoppingList>
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        if ($argument->getType() !== ShoppingList::class) {
            return [];
        }

        $listId = $request->attributes->get('listId');
        if (!$listId) {
            return [];
        }

        $token = $this->tokenStorage->getToken();
        if (!$token) {
            throw new NotFoundHttpException('Shopping list not found');
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            throw new NotFoundHttpException('Shopping list not found');
        }

        $shoppingList = $this->shoppingListRepository->findOneBy([
            'id' => (int) $listId,
            'user' => $user,
        ]);

        if (!$shoppingList) {
            throw new NotFoundHttpException('Shopping list not found');
        }

        yield $shoppingList;
    }
}
