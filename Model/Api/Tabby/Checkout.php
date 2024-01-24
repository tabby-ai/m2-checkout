<?php

namespace Tabby\Checkout\Model\Api\Tabby;

use Magento\Framework\Exception\LocalizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Model\Api\Tabby;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

class Checkout extends Tabby
{
    const API_PATH = 'checkout';
    const API_VERSION = 'v2';

    /**
     * @param $storeId
     * @param $id
     * @return mixed
     * @throws LocalizedException
     * @throws NotFoundException
     */
    public function createSession($storeId, $data)
    {
        return $this->request($storeId, '', HttpMethod::METHOD_POST, $data);
    }
}
