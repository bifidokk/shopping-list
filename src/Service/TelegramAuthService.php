<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class TelegramAuthService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private string $botToken
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validateInitData(string $initData): ?array
    {
        parse_str($initData, $data);

        if (!isset($data['hash']) || !is_string($data['hash'])) {
            return null;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        ksort($data);

        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $dataCheckArr[] = $key.'='.(string) $value;
        }

        $dataCheckString = implode("\n", $dataCheckArr);

        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (!hash_equals($calculatedHash, $hash)) {
            return null;
        }

        if (isset($data['auth_date'])) {
            $authDate = (int) $data['auth_date'];
            $currentTime = time();

            if ($currentTime - $authDate > 86400) {
                return null;
            }
        }

        // @phpstan-ignore-next-line parse_str creates array with string keys in this context
        return $data;
    }

    /**
     * @param array<string, mixed> $telegramData
     */
    public function findOrCreateUser(array $telegramData): ?User
    {
        if (!isset($telegramData['user'])) {
            return null;
        }

        $userData = json_decode($telegramData['user'], true);

        if (!isset($userData['id'])) {
            return null;
        }

        $telegramId = (int) $userData['id'];

        $user = $this->userRepository->findOneBy([
            'telegramId' => $telegramId,
        ]);

        if ($user) {
            $user->setFirstName($userData['first_name'] ?? null);
            $user->setLastName($userData['last_name'] ?? null);
            $user->setUsername($userData['username'] ?? null);
            $user->setLanguageCode($userData['language_code'] ?? null);
            $user->setUpdatedAt(new \DateTime());
        } else {
            $user = new User();
            $user->setTelegramId($telegramId);
            $user->setFirstName($userData['first_name'] ?? null);
            $user->setLastName($userData['last_name'] ?? null);
            $user->setUsername($userData['username'] ?? null);
            $user->setLanguageCode($userData['language_code'] ?? null);

            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();

        return $user;
    }

    public function authenticate(string $initData): ?User
    {
        $validatedData = $this->validateInitData($initData);

        if (!$validatedData) {
            return null;
        }

        return $this->findOrCreateUser($validatedData);
    }
}
