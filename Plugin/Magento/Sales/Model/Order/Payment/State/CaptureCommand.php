<?php

namespace Tabby\Checkout\Plugin\Magento\Sales\Model\Order\Payment\State;

use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class CaptureCommand
{
    /**
     * Update notification msg for Tabby payment method in order to avoid not rendered phrases
     *
     * @param \Magento\Sales\Model\Order\Payment\State\CaptureCommand $command
     * @param ?Phrase $result
     * @param OrderPaymentInterface $payment
     * @return mixed|string
     */
    public function afterExecute(
        \Magento\Sales\Model\Order\Payment\State\CaptureCommand $command,
        $result,
        OrderPaymentInterface $payment
    ) {

        if (preg_match('#^tabby_#', $payment->getMethod()) && $payment->getExtensionAttributes()) {
            $result = $payment->getExtensionAttributes()->getNotificationMessage() ?: $result;
        }

        return ($result instanceof Phrase) ? $result->render() : $result;
    }
}
