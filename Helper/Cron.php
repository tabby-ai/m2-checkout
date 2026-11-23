<?php
namespace Tabby\Checkout\Helper;

use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Cron\Model\ResourceModel\Schedule\CollectionFactory;
use Magento\Framework\Intl\DateTimeFactory;

class Cron
{
    /**
     * @var ScheduleFactory
     */
    protected $_scheduleFactory;

    /**
     * @var CollectionFactory
     */
    protected $scheduleCollectionFactory;

    /**
     * @var DateTimeFactory
     */
    protected $dateTimeFactory;

    /**
     * @param ScheduleFactory $scheduleFactory
     * @param DateTimeFactory $dateTimeFactory
     * @param CollectionFactory $scheduleCollectionFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ScheduleFactory $scheduleFactory,
        DateTimeFactory $dateTimeFactory,
        CollectionFactory $scheduleCollectionFactory
    ) {
        $this->_scheduleFactory = $scheduleFactory;
        $this->dateTimeFactory = $dateTimeFactory;
        $this->scheduleCollectionFactory = $scheduleCollectionFactory;
    }

    /**
     * Check if our tasks executed by cron
     *
     * @return bool
     */
    public function isCronActive()
    {
        // check tasks success runs last hour
        $DatabaseDateTime = $this->dateTimeFactory->create('3 hour ago', new \DateTimeZone('GMT'));

        $pendingJobs = $this->scheduleCollectionFactory->create();
        $pendingJobs->addFieldToFilter('status', Schedule::STATUS_SUCCESS);
        $pendingJobs->addFieldToFilter('executed_at', ['gt' => $DatabaseDateTime]);
        $pendingJobs->addFieldToFilter('job_code', 'tabby_order_service');

        return $pendingJobs->count() > 0;
    }
}
