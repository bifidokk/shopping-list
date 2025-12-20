<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'telegram:set-webhook',
    description: 'Register webhook URL with Telegram Bot API',
)]
class TelegramSetWebhookCommand extends Command
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $botToken
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'Webhook URL (must be HTTPS)')
            ->addOption('allowed-updates', null, InputOption::VALUE_OPTIONAL, 'JSON array of allowed update types', '["message","edited_message"]')
            ->addOption('max-connections', null, InputOption::VALUE_OPTIONAL, 'Maximum allowed number of simultaneous HTTPS connections', '40')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $webhookUrl = $input->getArgument('url');
        $allowedUpdates = $input->getOption('allowed-updates');
        $maxConnections = (int) $input->getOption('max-connections');

        if (!str_starts_with($webhookUrl, 'https://')) {
            $io->error('Webhook URL must use HTTPS protocol');

            return Command::FAILURE;
        }

        $io->section('Setting Telegram Webhook');
        $io->text([
            'Bot Token: '.substr($this->botToken, 0, 10).'...',
            "Webhook URL: {$webhookUrl}",
            "Allowed Updates: {$allowedUpdates}",
            "Max Connections: {$maxConnections}",
        ]);

        try {
            $response = $this->httpClient->request(
                'POST',
                "https://api.telegram.org/bot{$this->botToken}/setWebhook",
                [
                    'json' => [
                        'url' => $webhookUrl,
                        'allowed_updates' => json_decode($allowedUpdates ?? '[]'),
                        'max_connections' => $maxConnections,
                    ],
                ]
            );

            $statusCode = $response->getStatusCode();
            $content = $response->toArray();

            if ($statusCode === 200 && $content['ok'] === true) {
                $io->success('Webhook successfully registered!');
                $io->text("Description: {$content['description']}");

                return Command::SUCCESS;
            } else {
                $io->error('Failed to set webhook');
                $encodedContent = json_encode($content, JSON_PRETTY_PRINT);
                $io->text($encodedContent !== false ? $encodedContent : 'Unable to encode response');

                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $io->error('Error setting webhook: '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
