<?php
namespace Tabby\Checkout\Api;

/**
 * Interface for managing guest order history information
 * @api
 * @since 1.0.0
 */
interface PaymentCancelInterface
{
    /**
     * @return string
     */
    public function cancelPayment();
}

