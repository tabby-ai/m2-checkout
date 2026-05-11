<?php
namespace Tabby\Checkout\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Tabby\Checkout\Gateway\Helper\Currency as CurrencyHelper;
use Tabby\Checkout\Gateway\Request\CaptureDataBuilder;

class RefundDataBuilder extends CaptureDataBuilder
{
    /**
     * Build payment array for request
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $creditmemo = $buildSubject['creditmemo'] ?? null;

        if (!$creditmemo) {
            $creditmemo = $paymentDO->getPayment()->getCreditmemo();
        }

        if (!$creditmemo) {
            $creditmemo = $this->registry->registry('current_creditmemo');
        }

        if (!$creditmemo) {
            $amount = SubjectReader::readAmount($buildSubject);
            return [
                'amount' => $paymentDO->getPayment()->formatAmount($amount)
            ];
        }

        return [
            'amount' => CurrencyHelper::getTabbyPrice($creditmemo, 'grand_total'),
            'capture_id' => $creditmemo->getInvoice()->getTransactionId(),
            'tax_amount' => CurrencyHelper::getTabbyPrice($creditmemo, 'tax_amount'),
            'shipping_amount' => CurrencyHelper::getTabbyPrice($creditmemo, 'shipping_amount'),
            'discount_amount' => ltrim(CurrencyHelper::getTabbyPrice($creditmemo, 'discount_amount'), '-'),
            'created_at' => null,
            'items' => $this->getItems($creditmemo)
        ];
    }
}
