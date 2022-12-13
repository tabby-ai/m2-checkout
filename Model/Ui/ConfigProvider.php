<?php

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

final class ConfigProvider implements ConfigProviderInterface
{

    const CODE = 'tabby_checkout';

    const KEY_PUBLIC_KEY = 'public_key';

    protected $orders;

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
    protected $_urlInterface;

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
     * @param Config $config
     * @param SessionManagerInterface $session
     * @param Session $_checkoutSession
     * @param BuyerHistory $buyerHistory
     * @param OrderHistory $orderHistory
     * @param Image $imageHelper
     * @param CollectionFactory $orderCollectionFactory
     * @param Repository $assetRepo
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     * @param Resolver $resolver
     * @param UrlInterface $urlInterface
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
        $this->_urlInterface = $urlInterface;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {

        return [
            'payment' => [
                self::CODE => [
                    'config' => $this->getTabbyConfig(),
                    'defaultRedirectUrl'    => $this->_urlInterface->getUrl('tabby/redirect'),
                    'payment' => $this->getPaymentObject(),
                    'storeGroupCode' => $this->storeManager->getGroup()->getCode(),
                    'lang' => $this->resolver->getLocale(),
                    'urls' => $this->getQuoteItemsUrls(),
                    'methods' => $this->getMethodsAdditionalInfo()
                ]
            ]
        ];
    }

    /**
     * @return array
     */
    private function getMethodsAdditionalInfo()
    {
        $result = [];
        foreach (\Tabby\Checkout\Gateway\Config\Config::ALLOWED_SERVICES as $method => $title) {
            $result[$method] = [
                'description_type' => (int)$this->config->getScopeConfig()->getValue('payment/' . $method . '/description_type',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->session->getStoreId()),
                'card_theme' => $this->config->getScopeConfig()->getValue('payment/' . $method . '/card_theme',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE, $this->session->getStoreId()) ?: 'default',
                'card_direction' => (int)$this->config->getScopeConfig()->getValue('payment/' . $method . '/description_type',
                    \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
                    $this->session->getStoreId()) == 1 ? 'narrow' : 'wide'
            ];
        }
        return $result;
    }

    /**
     * @return string
     */
    private function getFailPageUrl()
    {
        return $this->_urlInterface->getUrl('tabby/checkout/fail');
    }

    /**
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
     * @return array
     */
    private function getTabbyConfig()
    {
        $config = [];
        $config['apiKey'] = $this->config->getValue(self::KEY_PUBLIC_KEY, $this->session->getStoreId());
        if ($this->config->getValue('use_history', $this->session->getStoreId()) === 'no') {
            $config['use_history'] = false;
        }
        $params = array('_secure' => $this->request->isSecure());
        $config['hideMethods'] = (bool)$this->config->getValue('hide_methods', $this->session->getStoreId());
        $config['showLogo'] = (bool)$this->config->getValue('show_logo', $this->session->getStoreId());

        $logo_image = 'logo_' . $this->config->getValue('logo_color', $this->session->getStoreId());
        $config['paymentLogoSrc'] = $this->assetRepo->getUrlWithParams('Tabby_Checkout::images/' . $logo_image . '.png', $params);
        $config['paymentInfoSrc'] = $this->assetRepo->getUrlWithParams('Tabby_Checkout::images/info.png', $params);
        $config['paymentInfoHref'] = $this->assetRepo->getUrlWithParams('Tabby_Checkout::template/payment/info.html', $params);
        $config['local_currency'] = (bool)$this->config->getValue('local_currency', $this->session->getStoreId());

        $config['merchantUrls'] = $this->getMerchantUrls();
        $config['useRedirect'] = 1;

        return $config;
    }

    /**
     * @return array
     */
    protected function getMerchantUrls()
    {
        return [
            "success" => $this->_urlInterface->getUrl('tabby/result/success'),
            "cancel" => $this->_urlInterface->getUrl('tabby/result/cancel'),
            "failure" => $this->_urlInterface->getUrl('tabby/result/failure')
        ];
    }

    /**
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
