<?php
namespace Tabby\Checkout\Model;

use Tabby\Checkout\Gateway\Config\Config;
use Tabby\Checkout\Api\GuestOrderHistoryInformationInterface;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;


class GuestOrderHistoryInformation extends \Magento\Framework\Model\AbstractExtensibleModel
	implements GuestOrderHistoryInformationInterface {

    /**
     * @param \Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory
     * @param \Magento\Framework\Model\Context $context
     * @param \Magento\Framework\Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param \Magento\Framework\Model\ResourceModel\AbstractResource $resource
     * @param \Magento\Framework\Data\Collection\AbstractDb $resourceCollection
     * @param array $data
     */
    public function __construct(
		Config $config,
		\Magento\Sales\Model\ResourceModel\Order\CollectionFactory $orderCollectionFactory,
		\Magento\Sales\Model\ResourceModel\Order\Address\CollectionFactory $addressCollectionFactory,
		\Tabby\Checkout\Model\Ui\ConfigProvider $configProvider,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $resource, $resourceCollection, $data);

		$this->config = $config;
		$this->orderCollectionFactory = $orderCollectionFactory;
		$this->addressCollectionFactory = $addressCollectionFactory;
		$this->configProvider = $configProvider;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrderHistory($email, $phone)
    {
		$result = [];

		$processed = [];
		$orders = $this->orderCollectionFactory->create()
    		->addAttributeToFilter('customer_email', $email);

		foreach ($orders as $order) {
			if (in_array($order->getId(), $processed)) continue;
			$result[] = $this->configProvider->getOrderObject($order);
			$processed[] = $order->getId();
		}

		if ($this->config->getValue(Config::KEY_ORDER_HISTORY_USE_PHONE)) {
			$addresses = $this->addressCollectionFactory->create()
				->addAttributeToFilter('telephone', $phone);
			foreach ($addresses as $address) {
				$order = $address->getOrder();
				if (in_array($order->getId(), $processed)) continue;
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
