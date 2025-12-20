<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\TelegramWebhookDto;
use App\Service\TelegramWebhookService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/telegram')]
class TelegramWebhookController extends AbstractController
{
    public function __construct(
        private TelegramWebhookService $webhookService,
        private SerializerInterface $serializer,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/webhook', methods: ['POST'])]
    public function webhook(Request $request): JsonResponse
    {
        try {
            // Deserialize webhook payload
            $webhookDto = $this->serializer->deserialize(
                $request->getContent(),
                TelegramWebhookDto::class,
                'json'
            );

            $this->logger->info('Received Telegram webhook', [
                'update_id' => $webhookDto->update_id,
            ]);

            // Get message (prefer message over edited_message)
            $message = $webhookDto->message ?? $webhookDto->edited_message;

            if (!$message) {
                $this->logger->debug('No message in webhook payload');
                return $this->json(['ok' => true], Response::HTTP_OK);
            }

            // Validate message has required fields
            if (!$message->text || trim($message->text) === '') {
                $this->logger->debug('Message has no text');
                return $this->json(['ok' => true], Response::HTTP_OK);
            }

            if (!$message->from) {
                $this->logger->warning('Message has no from user');
                return $this->json(['ok' => true], Response::HTTP_OK);
            }

            // Process message through service layer
            $result = $this->webhookService->processMessage($message);

            // Send confirmation message if needed
            if ($result['should_respond']) {
                $this->webhookService->sendConfirmationMessage($message->chat->id, $result);
            }

            return $this->json(['ok' => true], Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->logger->error('Error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Return 200 OK to Telegram even on error to prevent retries
            return $this->json(['ok' => true], Response::HTTP_OK);
        }
    }
}
