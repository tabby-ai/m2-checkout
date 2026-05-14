<?php
namespace Tabby\Checkout\Gateway\Request;

use Magento\Payment\Gateway\Helper\SubjectReader;
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
            'amount' => $this->currencyHelper->getTabbyPrice($creditmemo, 'grand_total'),
            'capture_id' => $creditmemo->getInvoice()->getTransactionId(),
            'tax_amount' => $this->currencyHelper->getTabbyPrice($creditmemo, 'tax_amount'),
            'shipping_amount' => $this->currencyHelper->getTabbyPrice($creditmemo, 'shipping_amount'),
            'discount_amount' => ltrim($this->currencyHelper->getTabbyPrice($creditmemo, 'discount_amount'), '-'),
            'created_at' => null,
            'items' => $this->getItems($creditmemo)
        ];
    }
}
