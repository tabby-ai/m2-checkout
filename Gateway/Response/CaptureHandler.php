<?php
namespace Tabby\Checkout\Gateway\Response;

use Magento\Framework\Registry;
use Magento\Payment\Gateway\Data\PaymentDataObjectInterface;
use Magento\Payment\Gateway\Response\HandlerInterface;
use Magento\Framework\Exception\LocalizedException;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class CaptureHandler implements HandlerInterface
{
    /**
     * @var Registry
     */
    protected $registry;

    /**
     * @param Registry $registry
     */
    public function __construct(
        Registry $registry
    ) {
        $this->registry = $registry;
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
        if (!isset($handlingSubject['payment'])
            || !$handlingSubject['payment'] instanceof PaymentDataObjectInterface
        ) {
            throw new \InvalidArgumentException('Payment data object should be provided');
        }

        $paymentDO = $handlingSubject['payment'];

        $payment = $paymentDO->getPayment();

        $txn = $this->getLatestItem($response['captures']);

        $payment->setLastTransId($txn['id']);
        $payment->setTransactionId($txn['id'])
            ->setParentTransactionId($response['id'])
            ->setIsTransactionClosed(0);

        $order = $payment->getOrder();
        $invoice = $this->registry->registry('current_invoice');
        if ($invoice && CurrencyHelper::getTabbyCurrency($order) !== $order->getBaseCurrencyCode()) {
            $extensionAttributes = $payment->getExtensionAttributes();
            $extensionAttributes->setNotificationMessage(__(
                'Captured amount of %1 online.',
                $payment->getOrder()->getOrderCurrency()->formatTxt($this->getTabbyPrice($invoice, 'grand_total'))
            )->render());
        }

        return $this;
    }

    /**
     * Get latest item from array based on created_at property
     *
     * @param array $items
     * @return mixed
     */
    protected function getLatestItem($items)
    {
        $item = array_pop($items);
        foreach ($items as $temp) {
            if ($temp['created_at'] > $item['created_at']) {
                $item = $temp;
            }
        }
        return $item;
    }
}
