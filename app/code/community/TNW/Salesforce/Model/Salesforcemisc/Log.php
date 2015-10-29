<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync) 
 * Email: support@powersync.biz 
 * Developer: Evgeniy Ermolaev
 * Date: 29.10.15
 * Time: 14:42
 */ 
class TNW_Salesforce_Model_Salesforcemisc_Log extends Mage_Core_Model_Abstract
{
    /**
     * all available log message levels
     * @var array
     */
    protected static $allLevels = array();

    /**
     *
     */
    protected function _construct()
    {
        $this->_init('tnw_salesforce/salesforcemisc_log');
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
        if ($this->getLe)

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

        Mage::getSingleton('tnw_salesforce/salesforcemisc_log_file')->write($this->getMessage(), $this->getLevel());
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
            }
        }
        return parent::_afterSave();
    }


}