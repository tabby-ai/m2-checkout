<?php

namespace Tabby\Checkout\Model\Api\Tabby;

use Magento\Framework\Exception\LocalizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Model\Api\Tabby;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

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
        return $this->request($storeId, $id, HttpMethod::METHOD_PUT, $data);
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
        return $this->request($storeId, $id . '/captures', HttpMethod::METHOD_POST, $data);
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
        return $this->request($storeId, $id . '/refunds', HttpMethod::METHOD_POST, $data);
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
        return $this->request($storeId, $id . '/close', HttpMethod::METHOD_POST);
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
