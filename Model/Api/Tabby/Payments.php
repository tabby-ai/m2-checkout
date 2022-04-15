<?php

namespace Tabby\Checkout\Model\Api\Tabby;

use Magento\Framework\Exception\LocalizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Model\Api\Tabby;

class Payments extends Tabby
{
    const API_PATH = 'payments/';

    /**
     * @param $storeId
     * @param $id
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function getPayment($storeId, $id)
    {
        return $this->request($storeId, $id);
    }

    /**
     * @param $storeId
     * @param $id
     * @param $data
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function updatePayment($storeId, $id, $data)
    {
        return $this->request($storeId, $id, \Zend_Http_Client::PUT, $data);
    }

    /**
     * @param $storeId
     * @param $id
     * @param $data
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function capturePayment($storeId, $id, $data)
    {
        return $this->request($storeId, $id . '/captures', \Zend_Http_Client::POST, $data);
    }

    /**
     * @param $storeId
     * @param $id
     * @param $data
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function refundPayment($storeId, $id, $data)
    {
        return $this->request($storeId, $id . '/refunds', \Zend_Http_Client::POST, $data);
    }

    /**
     * @param $storeId
     * @param $id
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function closePayment($storeId, $id)
    {
        return $this->request($storeId, $id . '/close', \Zend_Http_Client::POST);
    }

    /**
     * @param $storeId
     * @param $id
     * @param $referenceId
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function updateReferenceId($storeId, $id, $referenceId)
    {
        $data = ["order" => ["reference_id" => $referenceId]];

        return $this->updatePayment($storeId, $id, $data);
    }

}
