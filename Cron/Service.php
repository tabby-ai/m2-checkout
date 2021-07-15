<?php

namespace Tabby\Checkout\Cron;

class Service
{
    protected $orders = null;

    protected $orderRepository;
    protected $searchCriteriaBuilder;
    protected $filterBuilder;

    /**
     * @param \Tabby\Checkout\Gateway\Config\Config $config,
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
     * @param \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
     * @param \Magento\Framework\Stdlib\DateTime\TimezoneInterface $date,
     * @param \Tabby\Checkout\Helper\Order $orderHelper
     **/
    public function __construct(
        \Tabby\Checkout\Gateway\Config\Config $config,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $date,
        \Tabby\Checkout\Helper\Order $orderHelper
    ) {
        $this->config                = $config;
        $this->orderRepository       = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->date                  = $date;
        $this->orderHelper           = $orderHelper;
    }

    public function execute()
    {

        foreach ($this->getOrderCollection() as $order) {
            // process only tabby orders
            if (preg_match("#^tabby_#", $order->getPayment()->getMethod())) {
                $this->orderHelper->expireOrder($order);
            }
        }
        return $this;
    }

    protected function getOrderCollection() {
        if (!$this->orders) {


            $dbTimeZone = new \DateTimeZone($this->date->getDefaultTimezone());
            $from = $this->date->date()
                ->setTimeZone($dbTimeZone)
                ->modify("-7 days")
                ->format('Y-m-d H:i:s');
            // max 1440 and min 15 mins
            $mins = max(15, min(1440, (int)$this->config->getValue('abandoned_timeout')));

            $to = $this->date->date()
                ->setTimeZone($dbTimeZone)
                ->modify("-$mins min")
                ->format('Y-m-d H:i:s');

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('state', [\Magento\Sales\Model\Order::STATE_PENDING_PAYMENT, \Magento\Sales\Model\Order::STATE_NEW], 'in')
                ->addFilter('created_at', $from, 'gt')
                ->addFilter('created_at', $to, 'lt')
                ->create();

            $this->orders = $this->orderRepository->getList($searchCriteria);
        }
        return $this->orders;
    }
}
