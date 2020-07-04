<?php

namespace Tabby\Checkout\Cron;

class Service
{
    protected $orders = null;

    protected $orderRepository;
    protected $searchCriteriaBuilder;
    protected $filterBuilder;

    /**
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     **/
    public function __construct(
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        \Magento\Framework\Api\SearchCriteriaBuilder $searchCriteriaBuilder,
        \Magento\Framework\Stdlib\DateTime\TimezoneInterface $date,
        \Tabby\Checkout\Helper\Order $orderHelper
    ) {
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
                ->modify("-1 day")
                ->format('Y-m-d H:i:s');
            $to = $this->date->date()
                ->setTimeZone($dbTimeZone)
                ->modify("-20 min")
                ->format('Y-m-d H:i:s');

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('state', 'new', 'eq')
                ->addFilter('created_at', $from, 'gt')
                ->addFilter('created_at', $to, 'lt')
                ->create();

            $this->orders = $this->orderRepository->getList($searchCriteria);
        }
        return $this->orders;
    }
}
