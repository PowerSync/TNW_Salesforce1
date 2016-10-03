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
 *
 */
class TNW_Salesforce_Model_Tool_Log extends Mage_Core_Model_Abstract
{
    const MESSAGE_LIMIT_SIZE = 65000;

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
    protected static $newTransaction = true;

    /**
     * prepare object
     */
    protected function _construct()
    {

        $this->_init('tnw_salesforce/tool_log');
    }

    public function isNewTransaction()
    {
        return empty(self::$transactionId) || self::$newTransaction;
    }

    /**
     * @return string
     */
    public function getTransactionId()
    {
        if (is_null(self::$transactionId)) {
            self::$transactionId = uniqid();
        } else {
            self::$newTransaction = false;
        }

        return self::$transactionId;
    }

    /**
     * returns all log message levels
     * @return array
     */
    public static function getAllLevels()
    {
        if (!self::$allLevels) {
            /**
             * find all available log message levels
             */
            $reflection = new ReflectionClass('Zend_Log');
            self::$allLevels = array_flip($reflection->getConstants());
        }

        return self::$allLevels;
    }

    /**
     * Processing object before save data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _beforeSave()
    {
        if (!$this->getData('transaction_id')) {
            $this->setData('transaction_id', $this->getTransactionId());
        }

        /**
         * @comment add session message is we in Admin area
         */
        if (
            Mage::app()->getStore()->isAdmin()
            && PHP_SAPI != 'cli'
        ) {

            $level = $this->getLevel();
            $message = $this->getMessage();

            $message = 'Salesforce integration: '. $message;
            switch ($level) {
                case Zend_Log::ERR:
                    Mage::getSingleton('adminhtml/session')->addUniqueMessages(Mage::getSingleton('core/message')->error($message));
                    break;
                case Zend_Log::NOTICE:
                    Mage::getSingleton('adminhtml/session')->addUniqueMessages(Mage::getSingleton('core/message')->notice($message));
                    break;
                case Zend_Log::WARN:
                    Mage::getSingleton('adminhtml/session')->addUniqueMessages(Mage::getSingleton('core/message')->warning($message));
                    break;
                default:
                    break;
            }
        }

        /**
         * Add config first time only
         */
        if ($this->isNewTransaction()) {
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