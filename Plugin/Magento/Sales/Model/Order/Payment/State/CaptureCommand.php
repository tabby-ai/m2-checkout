<?php

namespace Tabby\Checkout\Plugin\Magento\Sales\Model\Order\Payment\State;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class CaptureCommand {
    public function afterExecute(
        \Magento\Sales\Model\Order\Payment\State\CaptureCommand $command,
        $result,
        OrderPaymentInterface $payment
    ) {

        if (preg_match('#^tabby_#', $payment->getMethod()) && $payment->getExtensionAttributes()) {
            $result = $payment->getExtensionAttributes()->getNotificationMessage() ?: $result;
        }

        return ($result instanceof \Magento\Framework\Phrase) ? $result->render() : $result;
    }
}
