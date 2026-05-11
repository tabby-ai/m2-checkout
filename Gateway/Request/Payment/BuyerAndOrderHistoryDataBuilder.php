<?php
namespace Tabby\Checkout\Gateway\Request\Payment;

use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Tabby\Checkout\Model\Checkout\Payment\BuyerHistory;
use Tabby\Checkout\Model\Checkout\Payment\OrderHistory;


class BuyerAndOrderHistoryDataBuilder implements BuilderInterface
{
    /**
     * @var BuyerHistory
     */
    private $buyerHistory;

    /**
     * @var OrderHistory
     */
    private $orderHistory;

    /**
     * @param BuyerHistory $buyerHistory
     * @param OrderHistory $orderHistory
     */
    public function __construct(
        BuyerHistory $buyerHistory,
        OrderHistory $orderHistory
    ) {
        $this->buyerHistory = $buyerHistory;
        $this->orderHistory = $orderHistory;
    }

    /**
     * Build buyer array for request payment object
     *
     * @param  array $buildSubject
     * @return array
     */
    public function build(array $buildSubject): array
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);

        $order = $paymentDO->getPayment()->getOrder();

        $address = $order->getShippingAddress() ?: $order->getBillingAddress();

        $customer = $order->getCustomer();
        if (!$order->getCustomerIsGuest()) {
            $customer = $this->customerRepository->getById($order->getCustomerId());
        }

        $orderHistory = $this->orderHistory->getOrderHistoryObject(
            $customer,
            $order->getCustomerEmail(),
            $address ? $address->getTelephone() : null
        );

        return [
            'buyer_history' => $this->buyerHistory->getBuyerHistoryObject($customer, $orderHistory),
            'order_history' => $this->orderHistory->limitOrderHistoryObject($orderHistory),
        ];
    }
}
