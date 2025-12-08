<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;

class UserFixtures extends Fixture implements FixtureGroupInterface
{
    public const USER_TELEGRAM_ID = 123456789;
    public const USER_REFERENCE = 'user_test';

    public function load(ObjectManager $manager): void
    {
        $user = new User();
        $user->setTelegramId(self::USER_TELEGRAM_ID);
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setUsername('testuser');
        $user->setLanguageCode('en');
        $manager->persist($user);

        $this->addReference(self::USER_REFERENCE, $user);

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['test', 'user'];
    }
}
