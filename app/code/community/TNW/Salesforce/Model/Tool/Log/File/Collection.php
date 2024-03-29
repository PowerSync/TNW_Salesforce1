<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Model_Tool_Log_File_Collection
 */
class TNW_Salesforce_Model_Tool_Log_File_Collection extends Varien_Data_Collection_Filesystem
{
    /**
     * Folder, where all logs are stored
     *
     * @var string
     */
    protected $_baseDir;

    /**
     * Set collection specific parameters and make sure logs folder will exist
     */
    public function __construct()
    {
        parent::__construct();

        $this->_baseDir = Mage::getSingleton('tnw_salesforce/tool_log_file')->getLogDir();

        // check for valid base dir
        $ioProxy = Mage::getModel('tnw_salesforce/varien_io_file');
        $ioProxy->mkdir($this->_baseDir);
        if (!is_file($this->_baseDir . DS . '.htaccess') && $ioProxy->open(array('path' => $this->_baseDir))) {
            $ioProxy->write('.htaccess', 'deny from all', 0644);
        }

        $this->setCollectRecursively(false)
            ->setOrder('time', self::SORT_ORDER_DESC)
            ->addTargetDir($this->_baseDir);
    }

    /**
     * Get log-specific data from model for each row
     *
     * @param string $filename
     * @return array
     */
    protected function _generateRow($filename)
    {
        $row = parent::_generateRow($filename);
        foreach (Mage::getSingleton('tnw_salesforce/tool_log_file')->load($row['basename'], $this->_baseDir)
                     ->getData() as $key => $value) {
            $row[$key] = $value;
        }

//        $row['size'] = filesize($filename);
//        $row['id'] = $row['basename'];
        return $row;
    }
}
