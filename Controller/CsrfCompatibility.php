<?php

namespace Tabby\Checkout\Controller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;

if (interface_exists(CsrfAwareActionInterface::class)) {
    abstract class CsrfCompatibility extends Action implements CsrfAwareActionInterface
    {
        /**
         * @param RequestInterface $request
         * @return InvalidRequestException|null
         */
        public function createCsrfValidationException(
            RequestInterface $request
        ): ?InvalidRequestException {
            return null;
        }

        /**
         * @param RequestInterface $request
         * @return bool|null
         */
        public function validateForCsrf(RequestInterface $request): ?bool
        {
            return true;
        }
    }
} else {
    abstract class CsrfCompatibility extends Action
    {
    }
}
