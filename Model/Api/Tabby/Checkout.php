<?php

namespace Tabby\Checkout\Model\Api\Tabby;

use Magento\Framework\Exception\LocalizedException;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;
use Tabby\Checkout\Model\Api\Tabby;

class Checkout extends Tabby
{
    protected const API_PATH = 'checkout';

    /**
     * Create Tabby Checkout session
     *
     * @param int $storeId
     * @param array $data
     * @return mixed
     * @throws LocalizedException
     */
    public function createSession($storeId, $data)
    {
        $this->setCurrency($data['payment']['currency']);

        return $this->request($storeId, '', HttpMethod::METHOD_POST, $data);
    }
}
