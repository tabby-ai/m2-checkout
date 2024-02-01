<?php

namespace Tabby\Checkout\Model\Checkout\Payment;

use Magento\Catalog\Helper\Image;
use Magento\Checkout\Model\Session;
use Tabby\Checkout\Model\Checkout\Payment\BuyerHistory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Tabby\Checkout\Gateway\Config\Config;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Locale\Resolver;

class OrderHistory
{
    /**
     * @var array
     */
    protected $orders = [];

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var SessionManagerInterface
     */
    protected $session;

    /**
     * @var CollectionFactory
     */
    protected $orderCollectionFactory;

    /**
     * @var UrlInterface
     */
    protected $_urlInterface;

    protected const STATUS_MAP = [
        'complete' => 'complete',
        'closed' => 'refunded',
        'canceled' => 'canceled'
    ];

    /**
     * Constructor
     *
     * @param Config $config
     * @param SessionManagerInterface $session
     * @param CollectionFactory $orderCollectionFactory
     */
    public function __construct(
        Config $config,
        SessionManagerInterface $session,
        CollectionFactory $orderCollectionFactory
    ) {
        $this->config = $config;
        $this->session = $session;
        $this->orderCollectionFactory = $orderCollectionFactory;
    }

    /**
     * Returns order history limited by 10 records
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @param ?string $email
     * @param ?string $phone
     * @return array
     */
    public function getOrderHistoryLimited($customer, $email = null, $phone = null)
    {
        return $this->limitOrderHistoryObject($this->getOrderHistoryObject($customer, $email, $phone));
    }

    /**
     * Returns order history for Customer and by email/phone
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @param ?string $email
     * @param ?string $phone
     * @return array
     */
    public function getOrderHistoryObject($customer, $email = null, $phone = null)
    {
        $result = [];

        $processed = [];
        $attributes = [];
        if ($customer && $customer->getId()) {
            $attributes[] = [
                'attribute' => 'main_table.customer_id',
                'eq' => $customer->getId()
            ];
            if (!$email) {
                $email = $customer->getEmail();
            }
            if (!$phone && $this->config->getValue(Config::KEY_ORDER_HISTORY_USE_PHONE)) {
                $phone = [];
                foreach ($customer->getAddresses() as $address) {
                    if ($addressPhone = $address->getTelephone()) {
                        $phone[] = $addressPhone;
                    }
                }
            }
        }
        if ($email) {
            $attributes[] = [
                'attribute' => 'customer_email',
                'eq' => $email
            ];
        }
        if ($phone && $this->config->getValue(Config::KEY_ORDER_HISTORY_USE_PHONE)) {
            if (!is_array($phone)) {
                $phone = [$phone];
            }

            $attributes[] = [
                'attribute' => 'shipping_o_a.telephone',
                'in' => $phone
            ];
            $attributes[] = [
                'attribute' => 'billing_o_a.telephone',
                'in' => $phone
            ];
        }

        if (empty($attributes)) {
            return [];
        }

        $orders = $this->orderCollectionFactory->create()
            ->addAttributeToSelect('*')
            ->addAttributeToSearchFilter($attributes);
        // clean default where to build new one
        $orders->getSelect()->reset(\Magento\Framework\DB\Select::WHERE);
        $orders->addAttributeToFilter('state', ['in' => array_keys(self::STATUS_MAP)]);
        $fields = $values = [];
        foreach ($attributes as $a) {
            $fields[] = $a['attribute'];
            $values[] = $a;
        }
        $orders->addFieldToFilter($fields, $values);
        $orders->addAttributeToSort('entity_id', 'DESC')
            ->setPageSize(10)
            ->setCurPage(1);

        foreach ($orders as $order) {
            if (in_array($order->getId(), $processed)) {
                continue;
            }
            if (($tabbyObj = $this->getOrderObject($order)) !== false) {
                $result[] = $tabbyObj;
            }
            $processed[] = $order->getId();
        }

        return $result;
    }

    /**
     * Limit order history array
     *
     * @param array $order_history
     * @return array
     */
    public function limitOrderHistoryObject($order_history)
    {
        $order_history = $this->sortOrderHistoryOrders($order_history);
        if (count($order_history) > 10) {
            $order_history = array_slice($order_history, 0, 10);
        }
        return $order_history;
    }

    /**
     * Sort order history array
     *
     * @param array $order_history
     * @return array
     */
    public function sortOrderHistoryOrders($order_history)
    {
        usort($order_history, function ($a, $b) {
            // sort orderers by date descending
            return -strcmp($a['purchased_at'], $b['purchased_at']);
        });
        return $order_history;
    }

    /**
     * Build order object
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    public function getOrderObject($order)
    {
        $magentoStatus = $order->getState();
        $tabbyStatus = self::STATUS_MAP[$magentoStatus] ?? 'unknown';
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

    /**
     * Build order buyer object
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
    protected function getOrderBuyerObject($order)
    {
        return [
            'name' => $order->getCustomerName(),
            'email' => $order->getCustomerEmail(),
            'phone' => $this->getOrderCustomerPhone($order)
        ];
    }

    /**
     * Dig for customer phone in address book
     *
     * @param \Magento\Sales\Model\Order $order
     * @return string
     */
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

    /**
     * Build order items array
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
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

    /**
     * Build order shipping address array
     *
     * @param \Magento\Sales\Model\Order $order
     * @return array
     */
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

    /**
     * Format price for Tabby
     *
     * @param float $price
     * @return string
     */
    public function formatPrice($price)
    {
        return number_format($price, 2, '.', '');
    }
}
