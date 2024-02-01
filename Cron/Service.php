<?php

namespace Tabby\Checkout\Cron;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderSearchResultInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Tabby\Checkout\Gateway\Config\Config;

class Service
{
    /**
     * @var null|OrderSearchResultInterface
     */
    protected $orders = null;

    /**
     * @var OrderRepositoryInterface
     */
    protected $orderRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    protected $searchCriteriaBuilder;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var TimezoneInterface
     */
    protected $date;

    /**
     * @var \Tabby\Checkout\Helper\Order
     */
    protected $orderHelper;

    /**
     * @param Config $config ,
     * @param OrderRepositoryInterface $orderRepository ,
     * @param SearchCriteriaBuilder $searchCriteriaBuilder ,
     * @param TimezoneInterface $date ,
     * @param \Tabby\Checkout\Helper\Order $orderHelper
     **/
    public function __construct(
        Config $config,
        OrderRepositoryInterface $orderRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TimezoneInterface $date,
        \Tabby\Checkout\Helper\Order $orderHelper
    ) {
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->date = $date;
        $this->orderHelper = $orderHelper;
    }

    /**
     * Main method, order expire by cron logic
     *
     * @return $this
     */
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

    /**
     * Builds pending payment orders collection
     *
     * @return OrderSearchResultInterface
     */
    protected function getOrderCollection()
    {
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
                ->addFilter(
                    'state',
                    [Order::STATE_PENDING_PAYMENT, Order::STATE_NEW],
                    'in'
                )
                ->addFilter('created_at', $from, 'gt')
                ->addFilter('created_at', $to, 'lt')
                ->create();

            $this->orders = $this->orderRepository->getList($searchCriteria);
        }
        return $this->orders;
    }
}
