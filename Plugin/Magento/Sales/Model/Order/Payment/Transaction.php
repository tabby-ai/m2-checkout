<?php

namespace Tabby\Checkout\Plugin\Magento\Sales\Model\Order\Payment;

class Transaction
{
    /**
     * Update parent_id to null before transaction close called (in order to avoid infinity cycle)
     *
     * @param \Magento\Sales\Model\Order\Payment\Transaction $txn
     * @param bool? $messagePrependTo
     * @return null
     */
    public function beforeClose(
        \Magento\Sales\Model\Order\Payment\Transaction $txn,
        bool $shouldSave = true
    ) {
        if ($txn->getId() == $txn->getParentId()) {
            $txn->setParentId(null);
        }

        return null;
    }
}
