<?php
namespace Tabby\Checkout\Model\Ui;

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
     * Constructor
     *
     * @param Config $config
     * @param SessionManagerInterface $session
     */
    public function __construct(
        Config $config,
        SessionManagerInterface $session,
        \Magento\Checkout\Model\Session $_checkoutSession,
        \Magento\Catalog\Helper\Image $imageHelper,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        \Magento\Framework\View\Asset\Repository $assetRepo,
        \Magento\Framework\App\RequestInterface $request,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        Resolver $resolver,
        \Magento\Framework\UrlInterface $urlInterface
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
                    'config'            => $this->getTabbyConfig(),
                    'payment'           => $this->getPaymentObject(),
                    'storeGroupCode'    => $this->storeManager->getGroup()->getCode(),
                    'lang'              => $this->resolver->getLocale(),
                    'urls'              => $this->getQuoteItemsUrls()
                ]
            ]
        ];
    }
    private function getFailPageUrl() {
        return $this->_urlInterface->getUrl('tabby/checkout/fail');
    }
    private function getQuoteItemsUrls() {
        $result = [];

        foreach ($this->checkoutSession->getQuote()->getAllVisibleItems() as $item) {
            $product = $item->getProduct();
            $image = $this->imageHelper->init($product, 'product_page_image_large');
            $result[$item->getId()] = [
                'image_url'     => $image->getUrl(),
                'product_url'   => $product->getUrlInStore()
            ];
        }
        return $result;
    }
    private function getTabbyConfig() {
        $config = [];
        $config['apiKey'] = $this->config->getValue(self::KEY_PUBLIC_KEY, $this->session->getStoreId());
        if ($this->config->getValue('use_history', $this->session->getStoreId()) === 'no') {
            $config['use_history'] = false;
        }
        $params = array('_secure' => $this->request->isSecure());
        $config['hideMethods']  = (bool)$this->config->getValue('hide_methods', $this->session->getStoreId());
        $config['showLogo']  = (bool)$this->config->getValue('show_logo', $this->session->getStoreId());
        $logo_image = 'logo_' . $this->config->getValue('logo_color', $this->session->getStoreId());
        $config['paymentLogoSrc']  = $this->assetRepo->getUrlWithParams('Tabby_Checkout::images/'.$logo_image.'.png', $params);
        $config['paymentInfoSrc']  = $this->assetRepo->getUrlWithParams('Tabby_Checkout::images/info.png', $params);
        $config['paymentInfoHref'] = $this->assetRepo->getUrlWithParams('Tabby_Checkout::template/payment/info.html', $params);
        $config['addCountryCode'] = (bool)$this->config->getValue('add_country_code', $this->session->getStoreId());
        $config['local_currency'] = (bool)$this->config->getValue('local_currency', $this->session->getStoreId());
        if ($this->config->getValue('use_redirect', $this->session->getStoreId())) {
            $config['merchantUrls'] = $this->getMerchantUrls();
            $config['useRedirect']  = 1;
        } else {
            $config['useRedirect']  = 0;
        }
        //$config['services'] = $this->getAllowedServices();
        return $config;
    }
    protected function getMerchantUrls() {
        return [
            "success"   => $this->_urlInterface->getUrl('tabby/result/success'),
            "cancel"    => $this->_urlInterface->getUrl('tabby/result/cancel' ),
            "failure"   => $this->_urlInterface->getUrl('tabby/result/failure')
        ];
    }
    public function getAllowedServices() {
        $services = [];
        $allowed = $this->config->getValue('allowed_services');

        foreach (\Tabby\Checkout\Gateway\Config\Config::ALLOWED_SERVICES as $code => $title) {
            if (empty($allowed) || in_array($code, $allowed)) {
                $services[$code] = __($title);
            };
        }
        return $services;
    }
    private function getPaymentObject() {
        $payment = [];
        $payment['order_history'] = $this->getOrderHistoryObject();
        return $payment;
    }
    public function getOrderHistoryObject() {
        $order_history = [];

        if ($this->config->getValue('use_history', $this->session->getStoreId()) !== 'no') {
            foreach ($this->getOrders() as $order) {
                $order_history[] = $this->getOrderObject($order);
            }
        }
        return $order_history;
    }
    protected function getOrders() {
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
  public function getOrderObject($order) {
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
            'amount'            => $this->formatPrice($order->getGrandTotal()),
            'buyer'             => $this->getOrderBuyerObject($order),
            'items'             => $this->getOrderItemsObject($order),
            'payment_method'    => $order->getPayment()->getMethod(),
            'purchased_at'      => date(\DateTime::RFC3339, strtotime($order->getCreatedAt())),
            'shipping_address'  => $this->getOrderShippingAddressObject($order),
            'status'            => $tabbyStatus
        ];
        return $o;
    }
    protected function getOrderBuyerObject($order) {
        return [
            'name'  => $order->getCustomerName(),
            'email' => $order->getCustomerEmail(),
            'phone' => $this->getOrderCustomerPhone($order)
        ];
    }
    protected function getOrderCustomerPhone($order) {
        foreach ([$order->getBillingAddress(), $order->getShippingAddress()] as $address) {
            if (!$address) continue;
            if ($address->getTelephone()) return $address->getTelephone();
        }
        return null;
    }
    protected function getOrderItemsObject($order) {
        $result = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $result[] = [
                'ordered'       => (int)$item->getQtyOrdered(),
                'captured'      => (int)$item->getQtyInvoiced(),
                'refunded'      => (int)$item->getQtyRefunded(),
                'shipped'       => (int)$item->getQtyShipped(),
                'title'         => $item->getName(),
        'unit_price'    => $this->formatPrice($item->getPriceInclTax()),
        'tax_amount'    => $this->formatPrice($item->getTaxAmount())
            ];
        }
        return $result;
    }
    protected function getOrderShippingAddressObject($order) {
        if ($order->getShippingAddress()) {
            return [
                'address'   => implode(PHP_EOL, $order->getShippingAddress()->getStreet()),
                'city'      => $order->getShippingAddress()->getCity()
            ];
        } elseif ($order->getBillingAddress()) {
            return [
                'address'   => implode(PHP_EOL, $order->getBillingAddress()->getStreet()),
                'city'      => $order->getBillingAddress()->getCity()
            ];
        
        };
        return null;
    }
    public function formatPrice($price) {
        return number_format($price, 2, '.', '');
    }
}
