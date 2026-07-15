<?php

namespace App\Infrastructure\External\Messaging;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FonnteProvider implements MessagingProviderInterface
{
    private ?string $apiKey;
    private string $baseUrl = 'https://api.fonnte.com';

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? config('services.fonnte.api_key');
    }

    /**
     * Send a WhatsApp message via Fonnte
     */
    public function send(string $recipient, string $message): bool
    {
        if (!$this->isConfigured()) {
            Log::warning('Fonnte: API key not configured');
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->post("{$this->baseUrl}/send", [
                'target' => $this->formatPhoneNumber($recipient),
                'message' => $message,
                'countryCode' => '62', // Indonesia
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['status']) && $data['status'] === true) {
                    Log::info('Fonnte: Message sent successfully', [
                        'recipient' => $this->maskPhoneNumber($recipient),
                    ]);
                    return true;
                }

                Log::warning('Fonnte: API returned error', [
                    'response' => $data,
                ]);
                return false;
            }

            Log::warning('Fonnte: HTTP request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Fonnte: Exception during send', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send a WhatsApp message with image via Fonnte
     */
    public function sendWithImage(string $recipient, string $message, string $imageUrl): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])->post("{$this->baseUrl}/send", [
                'target' => $this->formatPhoneNumber($recipient),
                'message' => $message,
                'url' => $imageUrl,
                'countryCode' => '62',
            ]);

            return $response->successful() &&
                ($response->json('status') === true);
        } catch (\Exception $e) {
            Log::error('Fonnte: Exception during sendWithImage', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if Fonnte is configured
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
        return 'fonnte';
    }

    /**
     * Set API key (useful for per-tenant configuration)
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
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Remove leading zeros
        $phone = ltrim($phone, '0');

        // If it doesn't start with 62, add it
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
