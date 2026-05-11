<?php
namespace Tabby\Checkout\Gateway\Request;

use Magento\Framework\Registry;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;

class CaptureDataBuilder implements BuilderInterface
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
     * Build payment array for request
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $invoice = $buildSubject['invoice'] ?? null;

        if (!$invoice) {
            $invoice = $paymentDO->getPayment()->getCreatedInvoice();
        }

        if (!$invoice) {
            $invoice = $this->registry->registry('current_invoice');
        }

        if (!$invoice) {
            $amount = SubjectReader::readAmount($buildSubject);
            return [
                'amount' => $paymentDO->getPayment()->formatAmount($amount)
            ];
        }

        return [
            'amount' => CurrencyHelper::getTabbyPrice($invoice, 'grand_total'),
            'reference_id' => $invoice->getIncrementId(),
            'tax_amount' => CurrencyHelper::getTabbyPrice($invoice, 'tax_amount'),
            'shipping_amount' => CurrencyHelper::getTabbyPrice($invoice, 'shipping_amount'),
            'discount_amount' => CurrencyHelper::getTabbyPrice($invoice, 'discount_amount'),
            'items' => $this->getItems($invoice)
        ];
    }

    protected function getItems($invoice) {
        $items = [];

        foreach ($invoice->getAllItems() as $item) {
            if (!$item->getOrderItem()->getParentItem()) {
                $items[] = [
                    'title' => $item->getName() ?: '',
                    'quantity' => (int)$item->getQty(),
                    'unit_price' => CurrencyHelper::getItemTabbyPrice($invoice, $item, 'price_incl_tax'),
                    'reference_id' => $item->getProductId() . '|' . $item->getSku(),
                    'description' => $item->getName() ?: '',
                ];
            }
        }

        return $items;
    }
}
