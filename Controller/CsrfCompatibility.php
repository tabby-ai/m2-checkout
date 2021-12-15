<?php
namespace Tabby\Checkout\Controller;

if (interface_exists("\Magento\Framework\App\CsrfAwareActionInterface")) {
    abstract class CsrfCompatibility extends \Magento\Framework\App\Action\Action implements \Magento\Framework\App\CsrfAwareActionInterface {
        public function createCsrfValidationException(\Magento\Framework\App\RequestInterface $request): ? \Magento\Framework\App\Request\InvalidRequestException {
            return null;
        }

        public function validateForCsrf(\Magento\Framework\App\RequestInterface $request): ?bool {
            return true;
        }
    }
} else {
    abstract class CsrfCompatibility extends \Magento\Framework\App\Action\Action {}
}
