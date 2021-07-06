<?php

namespace Tabby\Checkout\Plugin\Magento\Sales\Model\Order;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class Payment {
    public function beforePrependMessage(
        \Magento\Sales\Model\Order\Payment $payment,
        $messagePrependTo
    ) {
        if ($creditmemo = $payment->getCreditmemo()) {
            $message = __('We refunded %1 online.', $payment->formatPrice($creditmemo->getBaseGrandTotal()))->render();
    
            if (strcmp($messagePrependTo, $message) === 0) {
                if (preg_match('#^tabby_#', $payment->getMethod()) && $payment->getExtensionAttributes()) {
                    $messagePrependTo = $payment->getExtensionAttributes()->getNotificationMessage() ?: $messagePrependTo;
                }
            }
        } 

        return ($messagePrependTo instanceof \Magento\Framework\Phrase) ? $messagePrependTo->render() : $messagePrependTo;
    }
}
