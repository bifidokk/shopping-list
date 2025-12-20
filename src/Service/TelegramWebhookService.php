<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\CreateShoppingListDto;
use App\Dto\TelegramMessageDto;
use App\Dto\TelegramUserDto;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class TelegramWebhookService
{
    public function __construct(
        private MessageParserService $messageParserService,
        private ItemService $itemService,
        private ShoppingListService $shoppingListService,
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private string $botToken
    ) {
    }

    /**
     * @return array{added_count: int, total_parsed: int, total_users: int, chat_type: string, should_respond: bool}
     */
    public function processMessage(TelegramMessageDto $message): array
    {
        $telegramUser = $message->from;

        if ($message->chat === null) {
            return [
                'added_count' => 0,
                'total_parsed' => 0,
                'total_users' => 0,
                'chat_type' => 'private',
                'should_respond' => false,
            ];
        }

        $chatType = $message->chat->type ?? 'private';
        $chatId = $message->chat->id;

        $itemNames = $this->messageParserService->parseItems($message->text ?? '');

        if (empty($itemNames)) {
            $this->logger->debug('No items parsed from message');

            return [
                'added_count' => 0,
                'total_parsed' => 0,
                'total_users' => 0,
                'chat_type' => $chatType,
                'should_respond' => false,
            ];
        }

        $this->logger->info('Parsed items from message', [
            'chat_type' => $chatType,
            'chat_id' => $chatId,
            'items_count' => count($itemNames),
        ]);

        $addedCount = 0;
        $totalUsers = 0;

        if (in_array($chatType, ['group', 'supergroup'], true)) {
            $groupMembers = $this->getGroupMembers($chatId);
            $totalUsers = count($groupMembers);

            $this->logger->info('Processing group message', [
                'chat_id' => $chatId,
                'members_count' => $totalUsers,
            ]);

            foreach ($groupMembers as $member) {
                if (!isset($member['user']['id'])) {
                    continue;
                }

                $user = $this->userRepository->findOneBy(['telegramId' => $member['user']['id']]);

                if ($user) {
                    $added = $this->addItemsToUserDefaultList($user, $itemNames);
                    $addedCount += $added;
                }
            }
        } else {
            $totalUsers = 1;

            if ($telegramUser === null) {
                return [
                    'added_count' => 0,
                    'total_parsed' => count($itemNames),
                    'total_users' => 0,
                    'chat_type' => $chatType,
                    'should_respond' => false,
                ];
            }

            $user = $this->userRepository->findOneBy(['telegramId' => $telegramUser->id]);

            if (!$user) {
                $user = $this->createUser($telegramUser);
            }

            $addedCount = $this->addItemsToUserDefaultList($user, $itemNames);
        }

        $this->logger->info('Items added to shopping list(s)', [
            'chat_type' => $chatType,
            'chat_id' => $chatId,
            'added_count' => $addedCount,
            'total_parsed' => count($itemNames),
            'total_users' => $totalUsers,
        ]);

        return [
            'added_count' => $addedCount,
            'total_parsed' => count($itemNames),
            'total_users' => $totalUsers,
            'chat_type' => $chatType,
            'should_respond' => true,
        ];
    }

    /**
     * @param array{added_count: int, total_parsed: int, total_users: int, chat_type: string, should_respond: bool} $processResult
     */
    public function sendConfirmationMessage(int $chatId, array $processResult): void
    {
        $message = $this->buildConfirmationMessage(
            $processResult['added_count'],
            $processResult['total_parsed'],
            $processResult['total_users'],
            $processResult['chat_type']
        );

        $this->sendTelegramMessage($chatId, $message);
    }

    private function createUser(TelegramUserDto $telegramUser): User
    {
        $this->logger->info('Creating new user', [
            'telegram_id' => $telegramUser->id,
        ]);

        $user = new User();
        $user->setTelegramId($telegramUser->id);
        $user->setFirstName($telegramUser->first_name);
        $user->setLastName($telegramUser->last_name);
        $user->setUsername($telegramUser->username);
        $user->setLanguageCode($telegramUser->language_code);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getGroupMembers(int $chatId): array
    {
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/getChatAdministrators";
            $data = json_encode(['chat_id' => $chatId]);

            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => $data,
                ],
            ];

            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);

            if ($result === false) {
                $this->logger->error('Failed to get group administrators', [
                    'chat_id' => $chatId,
                ]);

                return [];
            }

            $response = json_decode($result, true);

            if ($response['ok'] === true) {
                return $response['result'];
            }

            return [];
        } catch (\Exception $e) {
            $this->logger->error('Error getting group members', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param list<string> $itemNames
     */
    private function addItemsToUserDefaultList(User $user, array $itemNames): int
    {
        $defaultListId = $this->shoppingListService->getUserDefaultListId($user);

        if ($defaultListId === null) {
            $this->logger->info('Creating default shopping list for user', [
                'user_id' => $user->getTelegramId(),
            ]);

            $createDto = new CreateShoppingListDto();
            $createDto->name = 'Shopping List';
            $createDto->description = null;

            $defaultList = $this->shoppingListService->createShoppingList($createDto, $user);
            $defaultListId = $defaultList->getId();
        }

        if ($defaultListId === null) {
            $this->logger->error('Failed to get or create default shopping list', [
                'user_id' => $user->getTelegramId(),
            ]);

            return 0;
        }

        $shoppingList = $this->shoppingListService->findUserShoppingList($defaultListId, $user);

        if (!$shoppingList) {
            $this->logger->error('Failed to find default shopping list', [
                'user_id' => $user->getTelegramId(),
                'list_id' => $defaultListId,
            ]);

            return 0;
        }

        $addedCount = 0;
        foreach ($itemNames as $itemName) {
            $item = $this->itemService->addItemIfNotExists($itemName, $shoppingList);
            if ($item !== null) {
                ++$addedCount;
            }
        }

        return $addedCount;
    }

    private function sendTelegramMessage(int $chatId, string $text): void
    {
        try {
            $url = "https://api.telegram.org/bot{$this->botToken}/sendMessage";
            $data = json_encode([
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);

            $options = [
                'http' => [
                    'method' => 'POST',
                    'header' => 'Content-Type: application/json',
                    'content' => $data,
                ],
            ];

            $context = stream_context_create($options);
            $result = file_get_contents($url, false, $context);

            if ($result === false) {
                $this->logger->error('Failed to send Telegram message', [
                    'chat_id' => $chatId,
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Error sending Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildConfirmationMessage(int $addedCount, int $totalParsed, int $totalUsers, string $chatType): string
    {
        $isGroup = in_array($chatType, ['group', 'supergroup'], true);

        if ($addedCount === 0) {
            if ($totalParsed === 0) {
                return "No items found in your message. Try sending items like:\n\n".
                       "milk\n".
                       "bread\n".
                       'eggs';
            }

            $listText = $isGroup ? 'shopping lists' : 'shopping list';

            return "All items are already in your {$listText}!";
        }

        if ($isGroup) {
            $message = $addedCount === 1
                ? '✅ Added <b>1 item</b> to shopping lists!'
                : "✅ Added <b>{$addedCount} items</b> to shopping lists!";
        } else {
            $message = $addedCount === 1
                ? '✅ Added <b>1 item</b> to your shopping list!'
                : "✅ Added <b>{$addedCount} items</b> to your shopping list!";
        }

        if ($addedCount < $totalParsed) {
            $skippedCount = $totalParsed - $addedCount;
            $message .= $skippedCount === 1
                ? "\n(1 item was already in the list)"
                : "\n({$skippedCount} items were already in the list)";
        }

        return $message;
    }
}
