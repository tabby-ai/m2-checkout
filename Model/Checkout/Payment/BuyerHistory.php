<?php

namespace Tabby\Checkout\Model\Checkout\Payment;

class BuyerHistory
{
    /**
     * Builds buyer history object by customer and order history
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @param array $order_history
     * @return array
     */
    public function getBuyerHistoryObject($customer, $order_history)
    {
        return [
            "registered_since"  => $this->getRegisteredSince($customer),
            "loyalty_level"     => $this->getLoyaltyLevel($order_history)
        ];
    }

    /**
     * Format Customer created at date in needed format
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @return string|null
     */
    protected function getRegisteredSince($customer)
    {
        if ($customer) {
            $date = $customer->getCreatedAt();
            if ($date) {
                return (new \DateTime($date))->format("c");
            }
        }
        return null;
    }

    /**
     * Count customer orders with status complete
     *
     * @param array $order_history
     * @return int
     */
    protected function getLoyaltyLevel($order_history)
    {
        if ($order_history) {
            return count(array_filter($order_history, function ($order) {
                return ($order["status"] == 'complete');
            }));
        }
        return 0;
    }
}
