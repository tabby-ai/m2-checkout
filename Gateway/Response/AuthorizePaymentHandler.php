<?php
namespace Tabby\Checkout\Gateway\Response;

use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Sales\Model\Order\Payment\Transaction;
use Tabby\Checkout\Gateway\Helper\Data as DataHelper;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;
use Tabby\Checkout\Gateway\Helper\Transaction as TransactionHelper;

class AuthorizePaymentHandler implements HandlerInterface
{
    /**
     * @var CurrencyHelper
     */
    private $currencyHelper;

    /**
     * @param CurrencyHelper $currencyHelper
     */
    public function __construct(
        CurrencyHelper $currencyHelper
    ) {
        $this->currencyHelper = $currencyHelper;
    }

    /**
     * Handles transaction id
     *
     * @param array $handlingSubject
     * @param array $response
     * @return void
     */
    public function handle(array $handlingSubject, array $response)
    {
        $paymentDO = SubjectReader::ReadPayment($handlingSubject);
        $amount = SubjectReader::ReadAmount($handlingSubject);

        $payment = $paymentDO->getPayment();
        $order = $payment->getOrder();

        if ($this->currencyHelper->getTabbyCurrency($order) !== $order->getBaseCurrencyCode()) {
            $extensionAttributes = $payment->getExtensionAttributes();
            $extensionAttributes->setNotificationMessage(__(
                'Authorized amount of %1.',
                $order->getOrderCurrency()->formatTxt($order->getGrandTotal())
            )->render());
            $payment->setExtensionAttributes($extensionAttributes);
        }

        $payment->setLastTransId($payment->getAdditionalInformation(DataHelper::PAYMENT_ID_FIELD));
        $payment->setTransactionId($payment->getAdditionalInformation(DataHelper::PAYMENT_ID_FIELD))
            ->setIsTransactionClosed(false);

        $payment->setBaseAmountAuthorized($amount);

        if ($response['status'] == 'CLOSED') {
            $payment->setIsTransactionClosed(true);
        }

        unset($response['order_history']);
        unset($response['meta']);
        $payment->setTransactionAdditionalInfo(
            Transaction::RAW_DETAILS,
            TransactionHelper::packPaymentDetails($response)
        );
    }
}
