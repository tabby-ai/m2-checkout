<?php

namespace Tabby\Checkout\Controller;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\CsrfAwareActionInterface;

if (interface_exists(CsrfAwareActionInterface::class)) {
    include __DIR__ . '/CsrfCompatibilityNew.php';
} else {
    abstract class CsrfCompatibility extends Action
    {
    }
}
