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

    public const USER_2_TELEGRAM_ID = 987654321;
    public const USER_2_REFERENCE = 'user_test_2';

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

        // Second user for testing sharing
        $user2 = new User();
        $user2->setTelegramId(self::USER_2_TELEGRAM_ID);
        $user2->setFirstName('Second');
        $user2->setLastName('User');
        $user2->setUsername('testuser2');
        $user2->setLanguageCode('en');
        $manager->persist($user2);

        $this->addReference(self::USER_2_REFERENCE, $user2);

        $manager->flush();
    }

    public static function getGroups(): array
    {
        return ['test', 'user'];
    }
}
