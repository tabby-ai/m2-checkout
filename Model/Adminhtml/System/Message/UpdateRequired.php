<?php
namespace Tabby\Checkout\Model\Adminhtml\System\Message;

use Magento\Framework\Notification\MessageInterface;
use Magento\Framework\FlagManager;
use Magento\Framework\Module\ModuleList;

/**
* Class UpdateRequired
*/
class UpdateRequired implements MessageInterface
{
    /**
     * Message identity
     */
    const MESSAGE_IDENTITY = 'tabby_checkout_update_required_system_message';

    /**
     * Flag for available version
     */
    const FLAG_VERSION = 'tabby_checkout_available_version';

    /**
     * Flag for time available version last checked
     */
    const FLAG_CHECKED = 'tabby_checkout_available_version_checked';

    /**
     * @var FlagManager
     */
    protected $flagManager;

    /**
     * @var ModuleList
     */
    protected $moduleList;

    public function __construct(
        FlagManager $flagManager,
        ModuleList $moduleList
    ) {
        $this->flagManager = $flagManager;
        $this->moduleList = $moduleList;
    }
    /**
     * Retrieve unique system message identity
     *
     * @return string
     */
    public function getIdentity()
    {
        return self::MESSAGE_IDENTITY;
    }

    /**
     * Check whether the system message should be shown
     *
     * @return bool
     */
    public function isDisplayed()
    {
        if (version_compare($this->getInstalledVersion(), $this->getAvailableVersion(), '<'))
        {
            return true;
        }
        return false;
    }

    private function getInstalledVersion() {
        $moduleInfo = $this->moduleList->getOne('Tabby_Checkout');

        return $moduleInfo["setup_version"];
    }

    private function getAvailableVersion() {
        $available = $this->flagManager->getFlagData(self::FLAG_VERSION);

        if ($this->isRecheckRequired() || empty($available)) {
            $available = $this->updateAvailableVersionFlag();
        }

        return $available;
    }

    private function isRecheckRequired() {
        return time() - (int)$this->flagManager->getFlagData(self::FLAG_CHECKED) < 24 * 60 * 60;
    }

    private function updateAvailableVersionFlag() {
        $available = '1.0.0';
        try {
            $obj = json_decode(file_get_contents("https://packagist.org/packages/tabby/m2-checkout/stats.json"));
            uasort($obj->versions, 'version_compare');
            $available = array_pop($obj->versions);
            // save result
            $this->flagManager->saveFlag(self::FLAG_VERSION, $available);
            $this->flagManager->saveFlag(self::FLAG_CHECKED, time());
        } catch (\Exception $e) {
        }
        return $available;
    }

    /**
     * Retrieve system message text
     *
     * @return \Magento\Framework\Phrase
     */
    public function getText()
    {
        return sprintf(__('New version (%s) of Tabby module available. Your current version is \'%s\'.'), $this->getAvailableVersion(), $this->getInstalledVersion());
    }

    /**
     * Retrieve system message severity
     * Possible default system message types:
     * - MessageInterface::SEVERITY_CRITICAL
     * - MessageInterface::SEVERITY_MAJOR
     * - MessageInterface::SEVERITY_MINOR
     * - MessageInterface::SEVERITY_NOTICE
     *
     * @return int
     */
    public function getSeverity()
    {
        return self::SEVERITY_NOTICE;
    }
}
