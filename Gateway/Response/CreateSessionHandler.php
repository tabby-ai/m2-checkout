<?php
namespace Tabby\Checkout\Gateway\Response;

use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Framework\Exception\LocalizedException;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;

class CreateSessionHandler implements HandlerInterface
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
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $paymentDO = $handlingSubject['payment'];

        $payment = $paymentDO->getPayment();

        $payment->setIsTransactionClosed(false);
        $payment->setIsTransactionPending(true);
        $payment->setAdditionalInformation(DataHelper::PAYMENT_ID_FIELD, $response['payment']['id']);

        // Extract web_url from nested structure
        if (isset($response['configuration']['available_products']['installments'][0]['web_url'])) {
            $webUrl = $response['configuration']['available_products']['installments'][0]['web_url'];
            $payment->setAdditionalInformation(DataHelper::TABBY_WEB_URL, $webUrl);
        }
    }
}
