<?php

namespace App\Infrastructure\External\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramProvider implements MessagingProviderInterface
{
    private ?string $botToken;
    private string $baseUrl = 'https://api.telegram.org';

    public function __construct(?string $botToken = null)
    {
        $this->botToken = $botToken ?? config('services.telegram.bot_token');
    }

    /**
     * Send a Telegram message
     */
    public function send(string $chatId, string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Telegram: Bot token not configured');
            return false;
        }

        try {
            $response = Http::post(
                "{$this->baseUrl}/bot{$this->botToken}/sendMessage",
                [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                ]
            );

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['ok']) && $data['ok'] === true) {
                    Log::info('Telegram: Message sent successfully', [
                        'chat_id' => $chatId,
                    ]);
                    return true;
                }

                Log::warning('Telegram: API returned error', [
                    'response' => $data,
                ]);
                return false;
            }

            Log::warning('Telegram: HTTP request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Telegram: Exception during send', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a Telegram message with image
     */
    public function sendWithImage(string $chatId, string $caption, string $imageUrl): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::post(
                "{$this->baseUrl}/bot{$this->botToken}/sendPhoto",
                [
                    'chat_id' => $chatId,
                    'photo' => $imageUrl,
                    'caption' => $caption,
                    'parse_mode' => 'HTML',
                ]
            );

            return $response->successful() &&
                ($response->json('ok') === true);
        } catch (\Exception $e) {
            Log::error('Telegram: Exception during sendWithImage', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if Telegram is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken);
    }

    /**
     * Get provider name
     */
    public function getName(): string
    {
        return 'telegram';
    }

    /**
     * Set bot token
     */
    public function setBotToken(string $botToken): self
    {
        $this->botToken = $botToken;
        return $this;
    }

    /**
     * Get bot info (useful for verifying configuration)
     */
    public function getBotInfo(): ?array
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::get(
                "{$this->baseUrl}/bot{$this->botToken}/getMe"
            );

            if ($response->successful() && $response->json('ok')) {
                return $response->json('result');
            }

            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Send message to multiple chat IDs
     */
    public function broadcast(array $chatIds, string $message): array
    {
        $results = [];

        foreach ($chatIds as $chatId) {
            $results[$chatId] = $this->send($chatId, $message);
        }

        return $results;
    }
}
