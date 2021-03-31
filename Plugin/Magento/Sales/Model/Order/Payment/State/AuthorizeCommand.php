<?php

namespace Tabby\Checkout\Plugin\Magento\Sales\Model\Order\Payment\State;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class AuthorizeCommand {
    public function aroundExecute(
        \Magento\Sales\Model\Order\Payment\State\AuthorizeCommand $command,
        callable $proceed,
        OrderPaymentInterface $payment, $amount, OrderInterface $order
    ) {

        $result = $proceed($payment, $amount, $order);

        if (preg_match('#^tabby_#', $payment->getMethod()) && $payment->getExtensionAttributes()) {
            $result = $payment->getExtensionAttributes()->getNotificationMessage() ?: $result;
        }

        return $result;
    }
}
