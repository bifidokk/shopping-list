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
        if ($this->isSafe($initData)) {
            parse_str(rawurldecode($initData), $data);

            return $this->checkAuthDate($data);
        }

        $this->logger->warning('Telegram auth failed: missing hash or signature');

        return null;
    }

    public function isSafe(string $initData): bool
    {
        [$checksum, $sortedInitData] = $this->convertInitData($initData);
        $secretKey = hash_hmac('sha256', $this->botToken, 'WebAppData', true);
        $hash = bin2hex(hash_hmac('sha256', $sortedInitData, $secretKey, true));

        return strcmp($hash, $checksum) === 0;
    }

    private function convertInitData(string $initData): array
    {
        $initDataArray = explode('&', rawurldecode($initData));
        $needle = 'hash=';
        $hash = '';

        foreach ($initDataArray as &$data) {
            if (substr($data, 0, \strlen($needle)) === $needle) {
                $hash = substr_replace($data, '', 0, \strlen($needle));
                $data = null;
            }
        }

        $initDataArray = array_filter($initDataArray);
        sort($initDataArray);

        return [$hash, implode("\n", $initDataArray)];
    }


    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>|null
     */
    private function checkAuthDate(array $data): ?array
    {
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
