<?php
namespace Tabby\Checkout\Gateway\Response;

use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;
use Tabby\Checkout\Gateway\Response\CaptureHandler;

class RefundHandler extends CaptureHandler
{
    /**
     * Handles transaction id
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $handlingSubject['payment'];

        $payment = $paymentDO->getPayment();
        $creditmemo = $payment->getCreditmemo();

        $txn = $this->getLatestItem($response['refunds']);

        $payment->setLastTransId($txn['id'])
            ->setTransactionId($txn['id'])
            ->setParentTransactionId($txn['capture_id'])
            ->setIsTransactionClosed(0);

        $order = $payment->getOrder();
        if ($this->currencyHelper->getTabbyCurrency($order) !== $order->getBaseCurrencyCode()) {
            $extensionAttributes = $payment->getExtensionAttributes();
            $extensionAttributes->setNotificationMessage(__(
                'We refunded %1 online.',
                $payment->getOrder()->getOrderCurrency()->formatTxt($this->getTabbyPrice($creditmemo, 'grand_total'))
            )->render());
        }

        return $this;
    }
}
