<?php

namespace Tabby\Checkout\Gateway\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;

class Config extends \Magento\Payment\Gateway\Config\Config
{
    const CODE = 'tabby_api';

    const DEFAULT_PATH_PATTERN = 'tabby/%s/%s';

    const KEY_PUBLIC_KEY = 'public_key';
    const KEY_SECRET_KEY = 'secret_key';

    const KEY_ORDER_HISTORY_USE_PHONE = 'order_history_use_phone';

    const CREATE_PENDING_INVOICE = 'create_pending_invoice';
    const CAPTURE_ON = 'capture_on';
    const CAPTURED_STATUS = 'captured_status';
    const MARK_COMPLETE = 'mark_complete';
    const AUTHORIZED_STATUS = 'authorized_status';

    const ALLOWED_SERVICES = [
        'tabby_cc_installments' => "Credit Card installments",
        'tabby_installments' => "Pay in installments",
        'tabby_checkout' => "Pay after delivery"
    ];

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * Tabby config constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param null|string $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        $methodCode = self::CODE,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param null $storeId
     * @return mixed|null
     */
    public function getPublicKey($storeId = null)
    {
        return $this->getValue(self::KEY_PUBLIC_KEY, $storeId);
    }

    /**
     * @param null $storeId
     * @return mixed|null
     */
    public function getSecretKey($storeId = null)
    {
        return $this->getValue(self::KEY_SECRET_KEY, $storeId);
    }

    /**
     * @return ScopeConfigInterface
     */
    public function getScopeConfig()
    {
        return $this->scopeConfig;
    }
}
