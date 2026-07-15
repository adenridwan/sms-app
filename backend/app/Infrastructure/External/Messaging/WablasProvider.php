<?php

namespace App\Infrastructure\External\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WablasProvider implements MessagingProviderInterface
{
    private ?string $apiKey;
    private string $baseUrl = 'https://solo.wablas.com/api';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.wablas.api_key');
    }

    /**
     * Send a WhatsApp message via Wablas
     */
    public function send(string $recipient, string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Wablas: API key not configured');
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->post("{$this->baseUrl}/send-message", [
                'phone' => $this->formatPhoneNumber($recipient),
                'message' => $message,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] === true) {
                    Log::info('Wablas: Message sent successfully', [
                        'recipient' => $this->maskPhoneNumber($recipient),
                    ]);
                    return true;
                }

                Log::warning('Wablas: API returned error', [
                    'response' => $data,
                ]);
                return false;
            }

            Log::warning('Wablas: HTTP request failed', [
                'status' => $response->status(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Wablas: Exception during send', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a WhatsApp message with image via Wablas
     */
    public function sendWithImage(string $recipient, string $message, string $imageUrl): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->post("{$this->baseUrl}/send-image", [
                'phone' => $this->formatPhoneNumber($recipient),
                'caption' => $message,
                'image' => $imageUrl,
            ]);

            return $response->successful() &&
                ($response->json('status') === true);
        } catch (\Exception $e) {
            Log::error('Wablas: Exception during sendWithImage', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if Wablas is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get provider name
     */
    public function getName(): string
    {
        return 'wablas';
    }

    /**
     * Set API key
     */
    public function setApiKey(string $apiKey): self
    {
        $this->apiKey = $apiKey;
        return $this;
    }

    /**
     * Format phone number for Indonesian numbers
     */
    private function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        $phone = ltrim($phone, '0');

        if (!str_starts_with($phone, '62')) {
            $phone = '62' . $phone;
        }

        return $phone;
    }

    /**
     * Mask phone number for logging
     */
    private function maskPhoneNumber(string $phone): string
    {
        if (strlen($phone) <= 6) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, 4) . str_repeat('*', strlen($phone) - 6) . substr($phone, -2);
    }
}
