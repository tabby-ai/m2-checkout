<?php

namespace Tabby\Checkout\Controller\Result;

use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\ResultFactory;
use Tabby\Checkout\Controller\CsrfCompatibility;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\Layout;
use Tabby\Checkout\Helper\Order;
use Tabby\Checkout\Api\WebhookProcessorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\App\Emulation;

class Webhook extends CsrfCompatibility
{
    /**
     * @var WebhookProcessorInterface
     */
    protected $webhookProcessor;

    /**
     * Webhook constructor.
     *
     * @param Context $context
     * @param WebhookProcessorInterface $webhookProcessor
     */
    public function __construct(
        Context $context,
        WebhookProcessorInterface $webhookProcessor
    ) {
        $this->webhookProcessor = $webhookProcessor;
        
        parent::__construct($context);
    }

    /**
     * Webhook process method
     *
     * @return ResponseInterface|ResultInterface|Layout
     */
    public function execute()
    {
        $webhook = $this->getRequest()->getContent();

        $json = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $json->setData(['success' => $this->webhookProcessor->processPaymentUpdate($webhook)]);

        return $json;
    }
}
