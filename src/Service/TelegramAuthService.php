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
     * Validates Telegram initData from Mini App
     *
     * @param string $initData The raw initData string from Telegram
     * @return array|null Parsed data if valid, null otherwise
     */
    public function validateInitData(string $initData): ?array
    {
        parse_str($initData, $data);

        if (!isset($data['hash'])) {
            return null;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        // Sort data alphabetically by key
        ksort($data);

        // Build data check string
        $dataCheckArr = [];
        foreach ($data as $key => $value) {
            $dataCheckArr[] = $key . '=' . $value;
        }
        $dataCheckString = implode("\n", $dataCheckArr);

        // Calculate secret key
        $secretKey = hash_hmac('sha256', $this->botToken, "WebAppData", true);

        // Calculate hash
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        // Verify hash
        if (!hash_equals($calculatedHash, $hash)) {
            return null;
        }

        // Verify auth_date is recent (within 24 hours)
        if (isset($data['auth_date'])) {
            $authDate = (int) $data['auth_date'];
            $currentTime = time();

            if ($currentTime - $authDate > 86400) { // 24 hours
                return null;
            }
        }

        return $data;
    }

    /**
     * Finds or creates a user from Telegram data
     *
     * @param array $telegramData Validated Telegram data
     * @return User|null
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

        // Find existing user
        $user = $this->userRepository->findOneBy([
            'telegramId' => $telegramId
        ]);

        if ($user) {
            // Update user data
            $user->setFirstName($userData['first_name'] ?? null);
            $user->setLastName($userData['last_name'] ?? null);
            $user->setUsername($userData['username'] ?? null);
            $user->setLanguageCode($userData['language_code'] ?? null);
            $user->setUpdatedAt(new \DateTime());
        } else {
            // Create new user
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

    /**
     * Authenticates a user from initData string
     *
     * @param string $initData The raw initData from Telegram Mini App
     * @return User|null The authenticated user or null if invalid
     */
    public function authenticate(string $initData): ?User
    {
        $validatedData = $this->validateInitData($initData);

        if (!$validatedData) {
            return null;
        }

        return $this->findOrCreateUser($validatedData);
    }
}
