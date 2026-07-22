<?php

namespace Tabby\Checkout\Observer;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Tabby\Checkout\Cron\WebhookService;

class ConfigObserver implements ObserverInterface
{
    /**
     * @var WebhookService
     */
    protected $webhookService;

    /**
     * ConfigObserver constructor.
     *
     * @param WebhookService $webhookService
     */
    public function __construct(
        WebhookService $webhookService
    ) {
        $this->webhookService = $webhookService;
    }

    /**
     * Main method, check for webhooks to be registered with Tabby
     *
     * @param EventObserver $observer
     */
    public function execute(EventObserver $observer)
    {
        $this->webhookService->execute();
    }
}
