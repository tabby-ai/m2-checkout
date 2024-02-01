<?php
namespace Tabby\Checkout\Helper;

use Magento\Cron\Model\Schedule;
use Magento\Cron\Model\ScheduleFactory;
use Magento\Framework\Intl\DateTimeFactory;

class Cron
{
    /**
     * @var ScheduleFactory
     */
    protected $_scheduleFactory;

    /**
     * @var DateTimeFactory
     */
    protected $dateTimeFactory;

    /**
     * @param ScheduleFactory $scheduleFactory
     * @param DateTimeFactory $dateTimeFactory
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        ScheduleFactory $scheduleFactory,
        DateTimeFactory $dateTimeFactory
    ) {
        $this->_scheduleFactory = $scheduleFactory;
        $this->dateTimeFactory = $dateTimeFactory;
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

        $pendingJobs = $this->_scheduleFactory->create()->getCollection();
        $pendingJobs->addFieldToFilter('status', Schedule::STATUS_SUCCESS);
        $pendingJobs->addFieldToFilter('executed_at', ['gt' => $DatabaseDateTime]);
        $pendingJobs->addFieldToFilter('job_code', 'tabby_order_service');

        return $pendingJobs->count() > 0;
    }
}
