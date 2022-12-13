<?php

namespace Tabby\Checkout\Model\Checkout\Payment;

class BuyerHistory {

    public function getBuyerHistoryObject($customer, $order_history) {
        return [
            "registered_since"  => $this->getRegisteredSince($customer),
            "loyalty_level"     => $this->getLoyaltyLevel($order_history)
        ];
    }
    protected function getRegisteredSince($customer) {
        if ($customer) {
            $date = $customer->getCreatedAt();
            if ($date) {
                return (new \DateTime($date))->format("c");
            }
        }
        return null;

    }
    protected function getLoyaltyLevel($order_history) {
        if ($order_history) {
            return count(array_filter($order_history, function ($order) {
                return ($order["status"] == 'complete');
            }));
        }

        return 0;
    }

}
