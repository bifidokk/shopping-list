<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TelegramAuthService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private string $botToken,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validateInitData(string $initData): ?array
    {
        parse_str($initData, $data);

        if (!isset($data['hash']) || !is_string($data['hash'])) {
            $this->logger->warning('Telegram auth failed: missing or invalid hash');

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
            $this->logger->warning('Telegram auth failed: invalid signature');

            return null;
        }

        if (isset($data['auth_date'])) {
            $authDate = (int) $data['auth_date'];
            $currentTime = time();

            if ($currentTime - $authDate > 86400) {
                $this->logger->warning('Telegram auth failed: initData expired', [
                    'auth_date' => $authDate,
                    'age_seconds' => $currentTime - $authDate,
                ]);

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
            $this->logger->warning('Telegram auth failed: missing user data');

            return null;
        }

        $userData = json_decode($telegramData['user'], true);

        if (!isset($userData['id'])) {
            $this->logger->warning('Telegram auth failed: missing user ID');

            return null;
        }

        $telegramId = (int) $userData['id'];

        $user = $this->userRepository->findOneBy([
            'telegramId' => $telegramId,
        ]);

        if ($user) {
            $this->logger->info('User authenticated', [
                'telegram_id' => $telegramId,
                'username' => $userData['username'] ?? null,
            ]);

            $user->setFirstName($userData['first_name'] ?? null);
            $user->setLastName($userData['last_name'] ?? null);
            $user->setUsername($userData['username'] ?? null);
            $user->setLanguageCode($userData['language_code'] ?? null);
            $user->setUpdatedAt(new \DateTime());
        } else {
            $this->logger->info('Creating new user', [
                'telegram_id' => $telegramId,
                'username' => $userData['username'] ?? null,
            ]);

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
        $this->logger->debug('Attempting Telegram authentication');

        $validatedData = $this->validateInitData($initData);

        if (!$validatedData) {
            $this->logger->warning('Telegram authentication failed');

            return null;
        }

        $user = $this->findOrCreateUser($validatedData);

        if ($user) {
            $this->logger->info('Telegram authentication successful', [
                'telegram_id' => $user->getTelegramId(),
            ]);
        }

        return $user;
    }
}
