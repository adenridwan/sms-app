<?php

namespace App\Domain\Notification\Services;

use App\Infrastructure\External\Messaging\TelegramProvider;
use App\Infrastructure\Persistence\Eloquent\Attendance\NotificationSetting;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private ?TelegramProvider $provider = null;
    private ?string $defaultChatId = null;

    /**
     * Initialize provider based on tenant settings
     */
    public function initializeForTenant(string $tenantId): self
    {
        $settings = NotificationSetting::getForTenant($tenantId);

        if (!$settings->isTelegramConfigured()) {
            $this->provider = null;
            return $this;
        }

        $this->provider = (new TelegramProvider())
            ->setBotToken($settings->telegram_bot_token);
        $this->defaultChatId = $settings->telegram_default_chat_id;

        return $this;
    }

    /**
     * Send a Telegram message to a specific chat
     */
    public function send(string $chatId, string $message): bool
    {
        if (!$this->provider) {
            Log::debug('Telegram: Provider not initialized');
            return false;
        }

        return $this->provider->send($chatId, $message);
    }

    /**
     * Send a Telegram message to default chat
     */
    public function sendToDefault(string $message): bool
    {
        if (!$this->defaultChatId) {
            Log::debug('Telegram: Default chat ID not set');
            return false;
        }

        return $this->send($this->defaultChatId, $message);
    }

    /**
     * Send a Telegram message with image
     */
    public function sendWithImage(string $chatId, string $message, string $imageUrl): bool
    {
        if (!$this->provider) {
            return false;
        }

        return $this->provider->sendWithImage($chatId, $message, $imageUrl);
    }

    /**
     * Broadcast message to multiple chats
     */
    public function broadcast(array $chatIds, string $message): array
    {
        if (!$this->provider) {
            return [];
        }

        return $this->provider->broadcast($chatIds, $message);
    }

    /**
     * Check if Telegram is configured and available
     */
    public function isAvailable(): bool
    {
        return $this->provider?->isConfigured() ?? false;
    }

    /**
     * Get bot info
     */
    public function getBotInfo(): ?array
    {
        return $this->provider?->getBotInfo();
    }

    /**
     * Send attendance notification
     */
    public function sendAttendanceNotification(
        string $chatId,
        string $template,
        array $replacements
    ): bool {
        $message = $this->parseTemplate($template, $replacements);
        return $this->send($chatId, $message);
    }

    /**
     * Parse template with replacements
     */
    private function parseTemplate(string $template, array $replacements): string
    {
        foreach ($replacements as $key => $value) {
            $template = str_replace("{{$key}}", $value, $template);
        }

        return $template;
    }
}
