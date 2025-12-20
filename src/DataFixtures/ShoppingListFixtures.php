<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\ShoppingList;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ShoppingListFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const SHOPPING_LIST_REFERENCE = 'shopping_list_test';

    public function load(ObjectManager $manager): void
    {
        /** @var User $user */
        $user = $this->getReference(UserFixtures::USER_REFERENCE, User::class);

        $shoppingList = new ShoppingList();
        $shoppingList->setName('Test Shopping List');
        $shoppingList->setDescription('A test shopping list for integration tests');
        $shoppingList->setUser($user);
        $shoppingList->setOwner($user);
        $shoppingList->setIsDefault(true);
        $manager->persist($shoppingList);

        $this->addReference(self::SHOPPING_LIST_REFERENCE, $shoppingList);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['test', 'shopping_list'];
    }
}
