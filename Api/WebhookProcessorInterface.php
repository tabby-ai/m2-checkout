<?php

namespace Tabby\Checkout\Api;

/**
 * Interface for webhook processor
 * @api
 * @since 5.0.0
 */
interface WebhookProcessorInterface
{
    /**
     * Process webhooks from Tabby
     *
     * @param string $id
     * @param string $status
     * @return string
     */
    public function process($id, $status) : string;

    /**
     * Process webhooks from json encoded string
     *
     * @param string $webhook
     * @return bool
     */
    public function processPaymentUpdate($webhook) : bool;
}
