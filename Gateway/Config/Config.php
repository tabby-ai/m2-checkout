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
	const MARK_COMPLETE = 'mark_complete';
	const AUTHORIZED_STATUS = 'authorized_status';

	const ALLOWED_SERVICES = [
		'installments'		=> "Pay in installments", 
		'pay_later'	=> "Pay after delivery"
	];

    var $_info = null;
    /**
     * Tabby config constructor
     *
     * @param ScopeConfigInterface $scopeConfig
     * @param Json $serializer
     * @param null|string $methodCode
     * @param string $pathPattern
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Json $serializer,
        $methodCode = self::CODE,
        $pathPattern = self::DEFAULT_PATH_PATTERN
    ) {
        parent::__construct($scopeConfig, $methodCode, $pathPattern);
        $this->serializer = $serializer;
    }

	public function getPublicKey($storeId = null) {
		return $this->getValue(self::KEY_PUBLIC_KEY, $storeId);
	}
	public function getSecretKey($storeId = null) {
		return $this->getValue(self::KEY_SECRET_KEY, $storeId);
	}
}
