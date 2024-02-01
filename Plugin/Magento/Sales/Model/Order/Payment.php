<?php

namespace Tabby\Checkout\Plugin\Magento\Sales\Model\Order;

use Magento\Framework\Phrase;

class Payment
{
    /**
     * Update notification msg for Tabby payment method in order to avoid not rendered phrases
     *
     * @param \Magento\Sales\Model\Order\Payment $payment
     * @param string|Phrase $messagePrependTo
     * @return mixed|string
     */
    public function beforePrependMessage(
        \Magento\Sales\Model\Order\Payment $payment,
        $messagePrependTo
    ) {
        if ($creditmemo = $payment->getCreditmemo()) {
            $message = __('We refunded %1 online.', $payment->formatPrice($creditmemo->getBaseGrandTotal()))->render();

            if (strcmp($messagePrependTo, $message) === 0) {
                if (preg_match('#^tabby_#', $payment->getMethod()) && $payment->getExtensionAttributes()) {
                    $messagePrependTo = $payment->getExtensionAttributes()->getNotificationMessage()
                        ?: $messagePrependTo;
                }
            }
        }

        return ($messagePrependTo instanceof Phrase) ? $messagePrependTo->render() : $messagePrependTo;
    }
}
