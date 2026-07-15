<?php

namespace App\Domain\Notification\Services;

use App\Infrastructure\External\Messaging\FonnteProvider;
use App\Infrastructure\External\Messaging\MessagingProviderInterface;
use App\Infrastructure\External\Messaging\WablasProvider;
use App\Infrastructure\Persistence\Eloquent\Attendance\NotificationSetting;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private ?MessagingProviderInterface $provider = null;

    /**
     * Initialize provider based on tenant settings
     */
    public function initializeForTenant(string $tenantId): self
    {
        $settings = NotificationSetting::getForTenant($tenantId);

        if (!$settings->isWhatsAppConfigured()) {
            $this->provider = null;
            return $this;
        }

        $this->provider = match ($settings->wa_provider) {
            'wablas' => (new WablasProvider())->setApiKey($settings->wa_api_key),
            default => (new FonnteProvider())->setApiKey($settings->wa_api_key),
        };

        return $this;
    }

    /**
     * Send a WhatsApp message
     */
    public function send(string $phone, string $message): bool
    {
        if (!$this->provider) {
            Log::debug('WhatsApp: Provider not initialized');
            return false;
        }

        return $this->provider->send($phone, $message);
    }

    /**
     * Send a WhatsApp message with image
     */
    public function sendWithImage(string $phone, string $message, string $imageUrl): bool
    {
        if (!$this->provider) {
            return false;
        }

        return $this->provider->sendWithImage($phone, $message, $imageUrl);
    }

    /**
     * Check if WhatsApp is configured and available
     */
    public function isAvailable(): bool
    {
        return $this->provider?->isConfigured() ?? false;
    }

    /**
     * Send attendance notification
     */
    public function sendAttendanceNotification(
        string $phone,
        string $template,
        array $replacements
    ): bool {
        $message = $this->parseTemplate($template, $replacements);
        return $this->send($phone, $message);
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

    /**
     * Get provider name
     */
    public function getProviderName(): string
    {
        return $this->provider?->getName() ?? 'none';
    }
}
