<?php

namespace Tabby\Checkout\Model\Api\Tabby;

use Magento\Framework\Exception\LocalizedException;
use Tabby\Checkout\Exception\NotFoundException;
use Tabby\Checkout\Model\Api\Tabby;
use Tabby\Checkout\Model\Api\Http\Method as HttpMethod;

class Checkout extends Tabby
{
    protected const API_PATH = 'checkout';
    protected const API_VERSION = 'v2';

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
        return $this->request($storeId, '', HttpMethod::METHOD_POST, $data);
    }
}
