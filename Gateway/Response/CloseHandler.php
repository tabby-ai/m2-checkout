<?php
namespace Tabby\Checkout\Gateway\Response;

use Magento\Payment\Gateway\Response\HandlerInterface;

class CloseHandler implements HandlerInterface
{
    /**
     * Handles transaction close
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = $handlingSubject['payment'];

        $payment = $paymentDO->getPayment();

        $payment->setIsTransactionClosed(true);

        return $this;
    }
}
