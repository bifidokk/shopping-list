<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Item;
use App\Entity\ShoppingList;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ItemFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        /** @var ShoppingList $shoppingList */
        $shoppingList = $this->getReference(ShoppingListFixtures::SHOPPING_LIST_REFERENCE, ShoppingList::class);

        // Item 1: Milk (not done)
        $item1 = new Item();
        $item1->setName('Milk');
        $item1->setQuantity(2);
        $item1->setUnit('liters');
        $item1->setNotes('Organic if possible');
        $item1->setIsDone(false);
        $item1->setShoppingList($shoppingList);
        $manager->persist($item1);

        // Item 2: Bread (done)
        $item2 = new Item();
        $item2->setName('Bread');
        $item2->setQuantity(1);
        $item2->setUnit('loaf');
        $item2->setIsDone(true);
        $item2->setShoppingList($shoppingList);
        $manager->persist($item2);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ShoppingListFixtures::class,
        ];
    }

    public static function getGroups(): array
    {
        return ['test', 'item'];
    }
}
