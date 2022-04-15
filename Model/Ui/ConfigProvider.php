<?php

namespace Tabby\Checkout\Model\Ui;

use Magento\Catalog\Helper\Image;
use Magento\Checkout\Model\Session;
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
     * Constructor
     *
     * @param Config $config
     * @param SessionManagerInterface $session
     * @param Session $_checkoutSession
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
        $config['paymentLogoSrc'] = $this->assetRepo->getUrlWithParams('Tabby_Checkout::images/' . $logo_image . '.png',
            $params);
        $config['paymentInfoSrc'] = $this->assetRepo->getUrlWithParams('Tabby_Checkout::images/info.png', $params);
        $config['paymentInfoHref'] = $this->assetRepo->getUrlWithParams('Tabby_Checkout::template/payment/info.html',
            $params);
        $config['addCountryCode'] = (bool)$this->config->getValue('add_country_code', $this->session->getStoreId());
        $config['local_currency'] = (bool)$this->config->getValue('local_currency', $this->session->getStoreId());

        // #5 force always use redirect
        if (true || $this->config->getValue('use_redirect', $this->session->getStoreId())) {
            $config['merchantUrls'] = $this->getMerchantUrls();
            $config['useRedirect'] = 1;
        } else {
            $config['useRedirect'] = 0;
        }
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
        $payment['order_history'] = $this->getOrderHistoryObject();
        return $payment;
    }

    /**
     * @return array
     */
    public function getOrderHistoryObject()
    {
        $order_history = [];

        if ($this->config->getValue('use_history', $this->session->getStoreId()) !== 'no') {
            foreach ($this->getOrders() as $order) {
                $order_history[] = $this->getOrderObject($order);
            }
        }
        return $order_history;
    }

    /**
     * @return array|Collection
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function getOrders()
    {
        $customer = $this->checkoutSession->getQuote()->getCustomer();

        $this->orders = [];
        if (!$this->orders && $customer->getId()) {
            $this->orders = $this->orderCollectionFactory->create()->addFieldToSelect(
                '*'
            )->addFieldToFilter(
                'customer_id',
                $customer->getId()
            )->setOrder(
                'created_at',
                'desc'
            );
        }
        return $this->orders;

    }

    public function getOrderObject($order)
    {
        $magento2tabby = [
            'new' => 'new',
            'complete' => 'complete',
            'closed' => 'refunded',
            'canceled' => 'canceled',
            'processing' => 'processing',
            'pending_payment' => 'processing',
            'payment_review' => 'processing',
            'pending' => 'processing',
            'holded' => 'processing',
            'STATE_OPEN' => 'processing'
        ];
        $magentoStatus = $order->getState();
        $tabbyStatus = $magento2tabby[$magentoStatus] ?? 'unknown';
        $o = [
            'amount' => $this->formatPrice($order->getGrandTotal()),
            'buyer' => $this->getOrderBuyerObject($order),
            'items' => $this->getOrderItemsObject($order),
            'payment_method' => $order->getPayment()->getMethod(),
            'purchased_at' => date(\DateTime::RFC3339, strtotime($order->getCreatedAt())),
            'shipping_address' => $this->getOrderShippingAddressObject($order),
            'status' => $tabbyStatus
        ];
        return $o;
    }

    protected function getOrderBuyerObject($order)
    {
        return [
            'name' => $order->getCustomerName(),
            'email' => $order->getCustomerEmail(),
            'phone' => $this->getOrderCustomerPhone($order)
        ];
    }

    protected function getOrderCustomerPhone($order)
    {
        foreach ([$order->getBillingAddress(), $order->getShippingAddress()] as $address) {
            if (!$address) {
                continue;
            }
            if ($address->getTelephone()) {
                return $address->getTelephone();
            }
        }
        return null;
    }

    protected function getOrderItemsObject($order)
    {
        $result = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $result[] = [
                'ordered' => (int)$item->getQtyOrdered(),
                'captured' => (int)$item->getQtyInvoiced(),
                'refunded' => (int)$item->getQtyRefunded(),
                'shipped' => (int)$item->getQtyShipped(),
                'title' => $item->getName(),
                'unit_price' => $this->formatPrice($item->getPriceInclTax()),
                'tax_amount' => $this->formatPrice($item->getTaxAmount())
            ];
        }
        return $result;
    }

    protected function getOrderShippingAddressObject($order)
    {
        if ($order->getShippingAddress()) {
            return [
                'address' => implode(PHP_EOL, $order->getShippingAddress()->getStreet()),
                'city' => $order->getShippingAddress()->getCity()
            ];
        } elseif ($order->getBillingAddress()) {
            return [
                'address' => implode(PHP_EOL, $order->getBillingAddress()->getStreet()),
                'city' => $order->getBillingAddress()->getCity()
            ];

        };
        return null;
    }

    public function formatPrice($price)
    {
        return number_format($price, 2, '.', '');
    }
}
