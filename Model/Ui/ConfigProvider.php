<?php
/**
 * Config provider model
 */
namespace Tabby\Checkout\Model\Ui;

use Magento\Catalog\Helper\Image;
use Magento\Checkout\Model\Session;
use Tabby\Checkout\Model\Checkout\Payment\BuyerHistory;
use Tabby\Checkout\Model\Checkout\Payment\OrderHistory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\ResourceModel\Order\Collection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Tabby\Checkout\Gateway\Config\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\Resolver;

/**
 * Config Provider for checkout front-end
 */
class ConfigProvider implements ConfigProviderInterface
{

    public const CODE = 'tabby_checkout';

    protected const KEY_PUBLIC_KEY = 'public_key';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var SessionManagerInterface
     */
    protected $session;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var Image
     */
    protected $imageHelper;

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var Repository
     */
    protected $assetRepo;

    /**
     * @var RequestInterface
     */
    protected $request;

    /**
     * @var Resolver
     */
    protected $resolver;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var UrlInterface
     */
    protected $urlInterface;

    /**
     * @var BuyerHistory
     */
    protected $buyerHistory;

    /**
     * @var OrderHistory
     */
    protected $orderHistory;

    /**
     * Constructor
     *
     * @param Config                  $config
     * @param SessionManagerInterface $session
     * @param Session                 $_checkoutSession
     * @param BuyerHistory            $buyerHistory
     * @param OrderHistory            $orderHistory
     * @param Image                   $imageHelper
     * @param CollectionFactory       $orderCollectionFactory
     * @param Repository              $assetRepo
     * @param RequestInterface        $request
     * @param StoreManagerInterface   $storeManager
     * @param Resolver                $resolver
     * @param UrlInterface            $urlInterface
     */
    public function __construct(
        Config $config,
        SessionManagerInterface $session,
        Session $_checkoutSession,
        BuyerHistory $buyerHistory,
        OrderHistory $orderHistory,
        Image $imageHelper,
        CollectionFactory $orderCollectionFactory,
        Repository $assetRepo,
        RequestInterface $request,
        StoreManagerInterface $storeManager,
        Resolver $resolver,
        UrlInterface $urlInterface
    ) {
        $this->config = $config;
        $this->session = $session;
        $this->checkoutSession = $_checkoutSession;
        $this->buyerHistory = $buyerHistory;
        $this->orderHistory = $orderHistory;
        $this->imageHelper = $imageHelper;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->assetRepo = $assetRepo;
        $this->request = $request;
        $this->resolver = $resolver;
        $this->storeManager = $storeManager;
        $this->urlInterface = $urlInterface;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        // bypass config for promotions only mode
        if ($this->config->getValue('plugin_mode', $this->session->getStoreId()) == '1') {
            return [];
        }

        return [
            'payment' => [
                self::CODE => [
                    'config' => $this->getTabbyConfig(),
                    'defaultRedirectUrl' => $this->urlInterface
                        ->getUrl('tabby/redirect'),
                    'payment' => $this->getPaymentObject(),
                    'storeGroupCode' => $this->storeManager->getGroup()->getCode(),
                    'lang' => $this->resolver->getLocale(),
                    'urls' => $this->getQuoteItemsUrls(),
                    'methods' => $this->_getMethodsAdditionalInfo()
                ]
            ]
        ];
    }

    /**
     * Provides additional configuration for payment methods
     *
     * @return array
     */
    private function _getMethodsAdditionalInfo()
    {
        $result = [];
        foreach (\Tabby\Checkout\Gateway\Config\Config::ALLOWED_SERVICES as $method => $title) {
            $description_type = (int)$this->config->getScopeConfig()->getValue(
                'payment/' . $method . '/description_type',
                \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                $this->session->getStoreId()
            );

            if ($method == 'tabby_installments' && $this->getInstallmentsCount() == 0 && $description_type < 2) {
                $description_type = 2;
            }

            $result[$method] = [
                'installments_count' => $method == 'tabby_installments' ? $this->getInstallmentsCount() : 4,
                'description_type' => $description_type,
                'card_theme' => $this->config->getScopeConfig()->getValue(
                    'payment/' . $method . '/card_theme',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $this->session->getStoreId()
                ) ?: 'default',
                'card_direction' => (int)$this->config->getScopeConfig()->getValue(
                    'payment/' . $method . '/description_type',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $this->session->getStoreId()
                ) == 1 ? 'narrow' : 'wide'
            ];
        }
        return $result;
    }

    /**
     * Provides installments count from theme promo config
     *
     * @return array
     */
    private function getInstallmentsCount()
    {
        return (
            strpos(
                $this->config->getScopeConfig()->getValue(
                    'tabby/tabby_api/promo_theme',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $this->session->getStoreId()
                ) ?: '',
                ':'
            ) === false
        ) ? 4 : 0;
    }
    /**
     * Payment fail page url
     *
     * @return string
     */
    private function getFailPageUrl()
    {
        return $this->urlInterface->getUrl('tabby/checkout/fail');
    }

    /**
     * Provides array of Quote Items Image/Product urls and category
     *
     * @return array
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    private function getQuoteItemsUrls()
    {
        $result = [];

        foreach ($this->checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            $image = $this->imageHelper->init($product, 'product_page_image_large');
            $category_name = '';
            if ($collection = $product->getCategoryCollection()->addNameToResult()) {
                if ($collection->getSize()) {
                    $category_name = $collection->getFirstItem()->getName();
                }
            }
            $result[$item->getId()] = [
                'image_url' => $image->getUrl(),
                'product_url' => $product->getUrlInStore(),
                'category' => $category_name
            ];
        }
        return $result;
    }

    /**
     * Provides Tabby Config for frontend
     *
     * @return array
     */
    private function getTabbyConfig()
    {
        $params = ['_secure' => $this->request->isSecure()];

        $logo_image = 'logo_' . $this->config->getValue('logo_color', $this->session->getStoreId());

        $config = [
            'apiKey'            => $this->config->getValue(self::KEY_PUBLIC_KEY, $this->session->getStoreId()),
            'hideMethods'       => (bool)$this->config->getValue('hide_methods', $this->session->getStoreId()),
            'local_currency'    => (bool)$this->config->getValue('local_currency', $this->session->getStoreId()),
            'showLogo'          => (bool)$this->config->getValue('show_logo', $this->session->getStoreId()),
            'paymentLogoSrc'    => $this->assetRepo->getUrlWithParams(
                'Tabby_Checkout::images/' . $logo_image . '.png',
                $params
            ),
            'paymentInfoSrc'    => $this->assetRepo->getUrlWithParams('Tabby_Checkout::images/info.png', $params),
            'paymentInfoHref'   => $this->assetRepo->getUrlWithParams(
                'Tabby_Checkout::template/payment/info.html',
                $params
            ),
            'merchantUrls'      => $this->getMerchantUrls(),
            'useRedirect'       => 1
        ];

        if ($this->config->getValue('use_history', $this->session->getStoreId()) === 'no') {
            $config['use_history'] = false;
        }

        return $config;
    }

    /**
     * Provides Merchant URLs for tabby create session request
     *
     * @return array
     */
    protected function getMerchantUrls()
    {
        return [
            "success" => $this->urlInterface->getUrl('tabby/result/success'),
            "cancel" => $this->urlInterface->getUrl('tabby/result/cancel'),
            "failure" => $this->urlInterface->getUrl('tabby/result/failure')
        ];
    }

    /**
     * Provides payment object for tabby create session request
     *
     * @return array
     */
    private function getPaymentObject()
    {
        $payment = [];
        $orderHistory = $this->orderHistory->getOrderHistoryObject($this->checkoutSession->getQuote()->getCustomer());
        $payment['order_history'] = $this->orderHistory->limitOrderHistoryObject($orderHistory);
        $payment['buyer_history'] = $this->buyerHistory->getBuyerHistoryObject(
            $this->checkoutSession->getQuote()->getCustomer(),
            $orderHistory
        );
        return $payment;
    }
}
