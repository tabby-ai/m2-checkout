<?php

namespace Tabby\Checkout\Model\Api\Tabby;

use Magento\Framework\Exception\LocalizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Model\Api\Tabby;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

class Payments extends Tabby
{
    protected const API_PATH = 'payments/';

    /**
     * Get payment object by id
     *
     * @param int $storeId
     * @param string $id
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function getPayment($storeId, $id)
    {
        return $this->request($storeId, $id);
    }

    /**
     * Update payment on Tabby by id
     *
     * @param int $storeId
     * @param string $id
     * @param array $data
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function updatePayment($storeId, $id, $data)
    {
        return $this->request($storeId, $id, HttpMethod::METHOD_PUT, $data);
    }

    /**
     * Capture payment on Tabby
     *
     * @param int $storeId
     * @param string $id
     * @param array $data
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function capturePayment($storeId, $id, $data)
    {
        return $this->request($storeId, $id . '/captures', HttpMethod::METHOD_POST, $data);
    }

    /**
     * Refund payment on Tabby
     *
     * @param int $storeId
     * @param store $id
     * @param array $data
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function refundPayment($storeId, $id, $data)
    {
        return $this->request($storeId, $id . '/refunds', HttpMethod::METHOD_POST, $data);
    }

    /**
     * Close payment on Tabby
     *
     * @param int $storeId
     * @param string $id
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function closePayment($storeId, $id)
    {
        return $this->request($storeId, $id . '/close', HttpMethod::METHOD_POST);
    }

    /**
     * Update payment reference id on Tabby
     *
     * @param int $storeId
     * @param string $id
     * @param string $referenceId
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
