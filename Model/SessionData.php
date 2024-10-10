<?php

namespace Tabby\Checkout\Model;

// AbstractExtensibleModel
use Magento\Framework\Model\AbstractExtensibleModel;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Data\Collection\AbstractDb;
// this class requirements
use Tabby\Checkout\Api\SessionDataInterface;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Tabby\Checkout\Model\Api\DdLog;
use Magento\Store\Model\StoreManagerInterface;
use Tabby\Checkout\Model\Api\Tabby\Checkout as CheckoutApi;

class SessionData extends AbstractExtensibleModel implements SessionDataInterface
{
    /**
     * @var RestRequest
     */
    protected $_request;

    /**
     * @var DdLog
     */
    protected $_ddlog;

    /**
     * @var StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var CheckoutApi
     */
    protected $_checkoutApi;

    /**
     * Class constructor
     *
     * @param RestRequest $request
     * @param DdLog $ddlog
     * @param StoreManagerInterface $storeManager
     * @param CheckoutApi $checkoutApi
     * @param Context $context
     * @param Registry $registry
     * @param ExtensionAttributesFactory $extensionFactory
     * @param AttributeValueFactory $customAttributeFactory
     * @param AbstractResource|null $resource
     * @param AbstractDb|null $resourceCollection
     * @param array $data
     */
    public function __construct(
        RestRequest $request,
        DdLog $ddlog,
        StoreManagerInterface $storeManager,
        CheckoutApi $checkoutApi,
        Context $context,
        Registry $registry,
        ExtensionAttributesFactory $extensionFactory,
        AttributeValueFactory $customAttributeFactory,
        AbstractResource $resource = null,
        AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct(
            $context,
            $registry,
            $extensionFactory,
            $customAttributeFactory,
            $resource,
            $resourceCollection,
            $data
        );

        $this->_request = $request;
        $this->_ddlog = $ddlog;
        $this->_storeManager = $storeManager;
        $this->_checkoutApi = $checkoutApi;
    }

    /**
     * @inheritdoc
     */
    public function createSession()
    {
        try {

            $data = json_decode($this->_request->getContent(), true);
            $session = $this->_checkoutApi->createSession(
                (int)$this->_storeManager->getStore()->getStoreId(),
                $data
            );
            return [[
                "status"                => $session->status,
                "payment_id"            => $session->payment->id,
                "available_products"    => $session->configuration->available_products
            ]];
        } catch (\Exception $e) {
            $this->_ddlog->log(
                'error',
                'Error creating prescoring session',
                $e,
                ['data' => $data]
            );
        }

        return [[
            'status'    => 'rejected'
        ]];
    }
}
