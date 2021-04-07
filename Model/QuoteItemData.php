<?php
namespace Tabby\Checkout\Model;

class QuoteItemData extends \Magento\Framework\Model\AbstractExtensibleModel
    implements \Tabby\Checkout\Api\QuoteItemDataInterface {

    /**
     * @param \Tabby\Checkout\Helper\Order $orderHelper
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
        \Magento\Quote\Model\QuoteIdMaskFactory $quoteIdMaskFactory,
        \Magento\Quote\Api\CartItemRepositoryInterface $quoteItemRepository,
        \Magento\Framework\Model\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Framework\Api\ExtensionAttributesFactory $extensionFactory,
        \Magento\Framework\Api\AttributeValueFactory $customAttributeFactory,
        \Magento\Framework\Model\ResourceModel\AbstractResource $resource = null,
        \Magento\Framework\Data\Collection\AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $extensionFactory, $customAttributeFactory, $resource, $resourceCollection, $data);

        $this->quoteIdMaskFactory  = $quoteIdMaskFactory;
        $this->quoteItemRepository = $quoteItemRepository;
    }


    /**
     * {@inheritdoc}
     */
    public function getGuestQuoteItemData($maskedId) {

        $quoteIdMask = $this->quoteIdMaskFactory->create()->load($maskedId, 'masked_id');

        return $this->getQuoteItemData($quoteIdMask->getQuoteId());
    }

    /**
     * {@inheritdoc}
     */
    public function getQuoteItemData($quoteId)
    {
        $quoteItemData = [];
        if ($quoteId) {
            $quoteItems = $this->quoteItemRepository->getList($quoteId);
            foreach ($quoteItems as $index => $quoteItem) {
                $quoteItemData[$index] = $quoteItem->toArray();
/*
                $quoteItemData[$index]['options'] = $this->getFormattedOptionValue($quoteItem);
                $quoteItemData[$index]['thumbnail'] = $this->imageHelper->init(
                    $quoteItem->getProduct(),
                    'product_thumbnail_image'
                )->getUrl();
                $quoteItemData[$index]['message'] = $quoteItem->getMessage();
*/
            }
        }
        return $quoteItemData;
    }
}
