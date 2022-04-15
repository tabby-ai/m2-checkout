<?php

namespace Tabby\Checkout\Model;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Sales\Model\ResourceModel\Order\Address\CollectionFactory;
use Tabby\Checkout\Gateway\Config\Config;
use Tabby\Checkout\Api\GuestOrderHistoryInformationInterface;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Tabby\Checkout\Model\Ui\ConfigProvider;

class GuestOrderHistoryInformation extends AbstractExtensibleModel implements GuestOrderHistoryInformationInterface
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var \Magento\Sales\Model\ResourceModel\Order\CollectionFactory
     */
    protected $orderCollectionFactory;


    /**
     * @var CollectionFactory
     */
    protected $addressCollectionFactory;

    /**
     * @var ConfigProvider
     */
    protected $configProvider;

    /**
     * @param Config $config
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param CollectionFactory $addressCollectionFactory
     * @param ConfigProvider $configProvider
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        Config $config,
        \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
        CollectionFactory $addressCollectionFactory,
        ConfigProvider $configProvider,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $resource,
            $resourceCollection, $data);

        $this->config = $config;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->addressCollectionFactory = $addressCollectionFactory;
        $this->configProvider = $configProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderHistory($email, $phone = null)
    {
        $result = [];

        $processed = [];
        $orders = $this->orderCollectionFactory->create()
            ->addAttributeToFilter('customer_email', $email);

        foreach ($orders as $order) {
            if (in_array($order->getId(), $processed)) {
                continue;
            }
            $result[] = $this->configProvider->getOrderObject($order);
            $processed[] = $order->getId();
        }

        if ($this->config->getValue(Config::KEY_ORDER_HISTORY_USE_PHONE) && $phone) {
            $addresses = $this->addressCollectionFactory->create()
                ->addAttributeToFilter('telephone', $phone);
            foreach ($addresses as $address) {
                $order = $address->getOrder();
                if (in_array($order->getId(), $processed)) {
                    continue;
                }
                $result[] = $this->configProvider->getOrderObject($order);
                $processed[] = $order->getId();
            }
        }
        if (count($result) > 10) {
            $result = array_slice($result, 0, 10);
        }
        return $result;
    }


}
