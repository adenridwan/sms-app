<?php

namespace App\Infrastructure\External\Messaging;

interface MessagingProviderInterface
{
    /**
     * Send a message to a recipient
     *
     * @param string $recipient Phone number or chat ID
     * @param string $message Message content
     * @return bool Success status
     */
    public function send(string $recipient, string $message): bool;

    /**
     * Send a message with an image
     *
     * @param string $recipient Phone number or chat ID
     * @param string $message Message content
     * @param string $imageUrl URL or path to image
     * @return bool Success status
     */
    public function sendWithImage(string $recipient, string $message, string $imageUrl): bool;

    /**
     * Check if the provider is properly configured
     */
    public function isConfigured(): bool;

    /**
     * Get provider name
     */
    public function getName(): string;
}
