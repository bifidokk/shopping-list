<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\TelegramAuthService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class TelegramAuthServiceTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private UserRepository $userRepository;
    private TelegramAuthService $service;
    private string $botToken = 'test_bot_token_123';

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->service = new TelegramAuthService(
            $this->entityManager,
            $this->userRepository,
            $this->botToken
        );
    }

    public function testValidateInitDataSuccess(): void
    {
        $authDate = time();

        // Build valid initData
        $data = [
            'auth_date' => (string) $authDate,
            'user' => '{"id":123456789,"first_name":"John"}',
        ];

        // Calculate hash
        $dataCheckString = "auth_date={$authDate}\nuser=" . $data['user'];
        $secretKey = hash_hmac('sha256', $this->botToken, "WebAppData", true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $initData = http_build_query($data) . '&hash=' . $hash;

        $result = $this->service->validateInitData($initData);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('auth_date', $result);
        $this->assertArrayHasKey('user', $result);
    }

    public function testValidateInitDataInvalidHash(): void
    {
        $authDate = time();
        $initData = http_build_query([
            'auth_date' => (string) $authDate,
            'user' => '{"id":123456789}',
            'hash' => 'invalid_hash'
        ]);

        $result = $this->service->validateInitData($initData);

        $this->assertNull($result);
    }

    public function testValidateInitDataMissingHash(): void
    {
        $initData = http_build_query([
            'auth_date' => (string) time(),
            'user' => '{"id":123456789}'
        ]);

        $result = $this->service->validateInitData($initData);

        $this->assertNull($result);
    }

    public function testValidateInitDataExpired(): void
    {
        // Auth date is more than 24 hours old
        $authDate = time() - 86401;

        $data = [
            'auth_date' => (string) $authDate,
            'user' => '{"id":123456789}',
        ];

        $dataCheckString = "auth_date={$authDate}\nuser=" . $data['user'];
        $secretKey = hash_hmac('sha256', $this->botToken, "WebAppData", true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $initData = http_build_query($data) . '&hash=' . $hash;

        $result = $this->service->validateInitData($initData);

        $this->assertNull($result);
    }

    public function testFindOrCreateUserNewUser(): void
    {
        $telegramData = [
            'user' => json_encode([
                'id' => 123456789,
                'first_name' => 'John',
                'last_name' => 'Doe',
                'username' => 'johndoe',
                'language_code' => 'en'
            ])
        ];

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['telegramId' => 123456789])
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (User $user) {
                return $user->getTelegramId() === 123456789
                    && $user->getFirstName() === 'John'
                    && $user->getLastName() === 'Doe'
                    && $user->getUsername() === 'johndoe'
                    && $user->getLanguageCode() === 'en';
            }));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->findOrCreateUser($telegramData);

        $this->assertInstanceOf(User::class, $result);
        $this->assertSame(123456789, $result->getTelegramId());
        $this->assertSame('John', $result->getFirstName());
        $this->assertSame('Doe', $result->getLastName());
    }

    public function testFindOrCreateUserExistingUser(): void
    {
        $existingUser = new User();
        $existingUser->setTelegramId(123456789);
        $existingUser->setFirstName('OldName');
        $existingUser->setLastName('OldLastName');

        $telegramData = [
            'user' => json_encode([
                'id' => 123456789,
                'first_name' => 'NewName',
                'last_name' => 'NewLastName',
                'username' => 'newusername',
                'language_code' => 'en'
            ])
        ];

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['telegramId' => 123456789])
            ->willReturn($existingUser);

        $this->entityManager->expects($this->never())
            ->method('persist');

        $this->entityManager->expects($this->once())
            ->method('flush');

        $result = $this->service->findOrCreateUser($telegramData);

        $this->assertSame($existingUser, $result);
        $this->assertSame('NewName', $result->getFirstName());
        $this->assertSame('NewLastName', $result->getLastName());
        $this->assertSame('newusername', $result->getUsername());
    }

    public function testFindOrCreateUserMissingUserData(): void
    {
        $telegramData = [];

        $result = $this->service->findOrCreateUser($telegramData);

        $this->assertNull($result);
    }

    public function testFindOrCreateUserInvalidUserData(): void
    {
        $telegramData = [
            'user' => json_encode(['no_id_field' => true])
        ];

        $result = $this->service->findOrCreateUser($telegramData);

        $this->assertNull($result);
    }

    public function testAuthenticateSuccess(): void
    {
        $authDate = time();
        $user = new User();
        $user->setTelegramId(123456789);

        $this->userRepository->expects($this->once())
            ->method('findOneBy')
            ->willReturn($user);

        $data = [
            'auth_date' => (string) $authDate,
            'user' => '{"id":123456789,"first_name":"John"}',
        ];

        $dataCheckString = "auth_date={$authDate}\nuser=" . $data['user'];
        $secretKey = hash_hmac('sha256', $this->botToken, "WebAppData", true);
        $hash = hash_hmac('sha256', $dataCheckString, $secretKey);

        $initData = http_build_query($data) . '&hash=' . $hash;

        $result = $this->service->authenticate($initData);

        $this->assertInstanceOf(User::class, $result);
    }

    public function testAuthenticateInvalidInitData(): void
    {
        $initData = 'invalid_data';

        $result = $this->service->authenticate($initData);

        $this->assertNull($result);
    }
}
