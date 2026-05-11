<?php
namespace Tabby\Checkout\Gateway\Helper;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\ModuleList;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Store\Model\ScopeInterface;

class Data
{
    /* Tabby config */
    public const CODE = 'tabby_api';
    public const DEFAULT_PATH_PATTERN = 'tabby/%s/%s';

    /* Method codes */
    public const CODE_INSTALLMENTS = 'tabby_installments';

    /* Public/secret keys */
    public const KEY_PUBLIC_KEY = 'public_key';
    public const KEY_SECRET_KEY = 'secret_key';

    /* Use local currency config key */
    public const KEY_LOCAL_CURRENCY = 'local_currency';
    public const KEY_AGGREGATE_CODE = 'aggregate_code';
    public const KEY_ABANDONED_TIMEOUT = 'abandoned_timeout';

    public const KEY_ORDER_HISTORY_USE_PHONE = 'order_history_use_phone';

    public const CREATE_PENDING_INVOICE = 'create_pending_invoice';
    public const CAPTURE_ON = 'capture_on';
    public const CAPTURED_STATUS = 'captured_status';
    public const MARK_COMPLETE = 'mark_complete';
    public const AUTHORIZED_STATUS = 'authorized_status';

    public const ALLOWED_SERVICES = [
        'tabby_cc_installments' => "Credit Card installments",
        'tabby_installments' => "Pay in installments",
        'tabby_checkout' => "Pay after delivery",
    ];

    public const CURRENCY_AED = 'AED';
    public const CURRENCY_KWD = 'KWD';
    public const CURRENCY_SAR = 'SAR';

    /* Payment Info additional information fields */
    public const PAYMENT_ID_FIELD = 'checkout_id';
    public const TABBY_WEB_URL = 'tabby_web_url';

    public static function isTabbyMethod($code) {
        return in_array($code, array_keys(self::ALLOWED_SERVICES));
    }
}
