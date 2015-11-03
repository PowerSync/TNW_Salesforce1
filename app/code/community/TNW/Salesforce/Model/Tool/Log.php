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
 *
 */
class TNW_Salesforce_Model_Tool_Log extends Mage_Core_Model_Abstract
{
    /**
     * all available log message levels
     * @var array
     */
    protected static $allLevels = array();

    /**
     * prepare object
     */
    protected function _construct()
    {

        $this->_init('tnw_salesforce/tool_log');
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

        Mage::getSingleton('tnw_salesforce/tool_log_file')->write($this->getMessage(), $this->getLevel());
        return parent::save();
    }

    /**
     * Processing object after save data
     *
     * @return Mage_Core_Model_Abstract
     */
    protected function _afterSave()
    {

        /**
         * @comment add session message is we in Admin area
         */
        if (
            Mage::app()->getStore()->isAdmin()
            && PHP_SAPI != 'cli'
        ) {

            $level = $this->getLevel();
            $message = $this->getMessage();

            switch ($level) {
                case Zend_Log::ERR:
                    Mage::getSingleton('adminhtml/session')->addError($message);
                    break;
                case Zend_Log::NOTICE:
                    Mage::getSingleton('adminhtml/session')->addNotice($message);
                    break;
                case Zend_Log::WARN:
                    Mage::getSingleton('adminhtml/session')->addWarning($message);
                    break;
                default:
                    break;
            }
        }
        return parent::_afterSave();
    }

    /**
     * Set/Get attribute wrapper
     * Added for saveTrace/saveWarning/saveError methods
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
                        default:
                            $level = Zend_Log::DEBUG;
                            break;
                    }

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