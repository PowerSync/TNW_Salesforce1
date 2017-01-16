<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Model_Tool_Log
 *
 * @method saveTrace($message)
 * @method saveNotice($message)
 * @method saveWarning($message)
 * @method saveError($message)
 * @method saveInfo($message)
 * @method saveSuccess($message)
 *
 * @method string getMessage()
 * @method int getLevel()
 * @method $this setMessage($message)
 * @method $this setLevel($level)
 *
 */
class TNW_Salesforce_Model_Tool_Log extends Mage_Core_Model_Abstract
{
    const MESSAGE_LIMIT_SIZE = 65000;
    const SUCCESS = 18;

    /**
     * all available log message levels
     * @var array
     */
    protected static $allLevels = array();

    /**
     * identify synchronization
     * @var
     */
    protected static $transactionId;

    /**
     * identify new synchronization
     * @var
     */
    protected static $newTransaction = array();

    /**
     * prepare object
     */
    protected function _construct()
    {

        $this->_init('tnw_salesforce/tool_log');
    }

    /**
     * @param null $website
     * @return bool
     */
    public function isNewTransaction($website = null)
    {
        $website = Mage::app()->getWebsite($website)->getCode();
        if(!isset(self::$newTransaction[$website])) {
            self::$newTransaction[$website] = false;
            return true;
        }

        return self::$newTransaction[$website];
    }

    /**
     * @return string
     */
    public function getTransactionId()
    {
        if (empty(self::$transactionId)) {
            self::$transactionId = uniqid();
        }

        return self::$transactionId;
    }

    /**
     * returns all log message levels
     * @return array
     */
    public static function getAllLevels()
    {
        return array(
            Zend_Log::EMERG => 'EMERG',
            Zend_Log::ALERT => 'ALERT',
            Zend_Log::CRIT => 'CRIT',
            Zend_Log::ERR => 'ERR',
            Zend_Log::WARN => 'WARN',
            Zend_Log::NOTICE => 'NOTICE',
            Zend_Log::INFO => 'INFO',
            Zend_Log::DEBUG => 'DEBUG',
            self::SUCCESS => 'SUCCESS',
        );
    }

    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _beforeSave()
    {
        $currentWebsite = Mage::app()->getWebsite();
        $this->setData('transaction_id', $this->getTransactionId());
        $this->setData('website_id', $currentWebsite->getId());

        /**
         * @comment add session message is we in Admin area
         */
        if (Mage::getSingleton('admin/session')->isLoggedIn()) {
            $message = sprintf('Salesforce integration: %s (Website: %s)', $this->getMessage(), $currentWebsite->getCode());
            switch ($this->getLevel()) {
                case Zend_Log::ERR:
                    Mage::getSingleton('adminhtml/session')->addUniqueMessages(Mage::getSingleton('core/message')->error($message));
                    break;
                case Zend_Log::NOTICE:
                    Mage::getSingleton('adminhtml/session')->addUniqueMessages(Mage::getSingleton('core/message')->notice($message));
                    break;
                case Zend_Log::WARN:
                    Mage::getSingleton('adminhtml/session')->addUniqueMessages(Mage::getSingleton('core/message')->warning($message));
                    break;
                case self::SUCCESS:
                    Mage::getSingleton('adminhtml/session')->addUniqueMessages(Mage::getSingleton('core/message')->success($message));
                    break;
                default:
                    break;
            }
        }

        /**
         * Add config first time only
         */
        if ($this->isNewTransaction($currentWebsite)) {
            $log = new self;
            $log->saveTrace("******************** New Transaction:{$this->getTransactionId()} ************");
        }

        $message = $this->getMessage();
        while (strlen($message) > self::MESSAGE_LIMIT_SIZE) {
            $log = new self;
            $log->setMessage(substr($message, 0, self::MESSAGE_LIMIT_SIZE));
            $log->setLevel($this->getLevel());
            $log->save();

            $message = substr($message, self::MESSAGE_LIMIT_SIZE);
        }

        $this->setMessage($message);
        return parent::_beforeSave();
    }


    /**
     * Save object data
     *
     * @return Mage_Core_Model_Abstract
     */
    public function save($message = null, $level = null)
    {
        if ($message) {
            $this->setMessage($message);
        }

        if ($level) {
            $this->setLevel($level);
        }

        return parent::save();
    }

    /**
     * Processing object after save data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterSave()
    {

        Mage::getSingleton('tnw_salesforce/tool_log_file')->write($this->getMessage(), $this->getLevel());

        return parent::_afterSave();
    }

    /**
     * Set/Get attribute wrapper
     * Added for saveTrace/saveWarning/saveError methods
     * create new record always
     *
     * @param   string $method
     * @param   array $args
     * @return  mixed
     */
    public function __call($method, $args)
    {
        if (count($args) == 1 && isset($args[0])) {
            switch (substr($method, 0, 4)) {
                case 'save' :
                    $level = strtoupper(substr($method, 4));

                    switch ($level) {
                        case 'ERROR':
                            $level = Zend_Log::ERR;
                            break;
                        case 'NOTICE':
                            $level = Zend_Log::NOTICE;
                            break;
                        case 'WARNING':
                            $level = Zend_Log::WARN;
                            break;
                        case 'INFO':
                            $level = Zend_Log::INFO;
                            break;
                        case 'SUCCESS':
                            $level = self::SUCCESS;
                            break;
                        default:
                            $level = Zend_Log::DEBUG;
                            break;
                    }

                    $this->unsetData(null);
                    $this->setMessage($args[0]);
                    $this->setLevel($level);
                    $this->save();

                    return $this;
                    break;
            }
        }

        return parent::__call($method, $args);
    }

}