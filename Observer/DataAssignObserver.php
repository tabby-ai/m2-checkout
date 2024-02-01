<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Exception\LocalizedException;
use Magento\Payment\Observer\AbstractDataAssignObserver;
use Magento\Sales\Api\Data\OrderPaymentInterface;

class DataAssignObserver extends AbstractDataAssignObserver
{
    private const ADDITIONAL_DATA_FIELD = 'additional_data';
    private const CHECKOUT_ID_FIELD = 'checkout_id';

    /**
     * Main method, assigns payment id to payment instance
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer)
    {
        $method = $this->readMethodArgument($observer);
        $data = $this->readDataArgument($observer);
        $paymentInfo = $method->getInfoInstance();

        $cid_path = OrderPaymentInterface::ADDITIONAL_DATA . '/' . self::CHECKOUT_ID_FIELD;
        if ($data->getDataByPath($cid_path) !== null) {
            $paymentInfo->setAdditionalInformation(
                self::CHECKOUT_ID_FIELD,
                $data->getDataByPath($cid_path)
            );
        }
    }
}
