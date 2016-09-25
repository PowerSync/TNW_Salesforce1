<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Model_Tool_Log_File
 *
 * @method string getPath()
 * @method string getName()
 * @method string setPath($path)
 * @method string getContent()
 * @method string setContent($content)
 */
class TNW_Salesforce_Model_Tool_Log_File  extends Varien_Object
{
    /**
     * save config dump in log file with first error
     * @var bool
     */
    protected static $saveConfig = true;

    /**
     * @var null
     */
    protected $_logDir = null;

    /**
     * @var string
     */
    protected $_salesforceLogDirName = 'salesforce';

    /**
     * @return TNW_Salesforce_Model_Varien_Io_File
     */
    protected static function _createIoFile()
    {
        return Mage::getModel('tnw_salesforce/varien_io_file');
    }

    /**
     * @comment module log dirrectory
     * @return string
     */
    public function getLogDir()
    {
        if (!$this->_logDir) {
            $this->_logDir = Mage::getBaseDir('log') . DS . $this->getSalesforceLogDirName();
            // check for valid base dir
            self::_createIoFile()->mkdir($this->_baseDir);
        }

        return $this->_logDir;
    }

    /**
     * @return string
     */
    public function getSalesforceLogDirName()
    {
        return $this->_salesforceLogDirName;
    }

    /**
     * set log file name
     * @param $name
     * @return $this
     */
    public function setName($name)
    {
        $filename = basename($name);
        $this->setData('filename', $filename);
        $this->setData('name', $name);

        return $this;
    }

    /**
     * Load log file info
     *
     * @param string $fileName
     * @param string $filePath
     * @return Mage_Log_Model_Log
     */
    public function load($fileName, $filePath = null)
    {
        $this->setName($fileName);
        $this->setPath($filePath);

        if (!$this->exists() && !$filePath) {
            $fileName = $this->prepareFullFilename($fileName);
        }

        $date = filectime($filePath . DS . $fileName);
        $size = filesize($filePath . DS . $fileName);

        $this->addData(array(
            'id' => $fileName,
            'time' => $date,
            'path' => $filePath,
            'extension' => '*',
            'display_name' => $fileName,
            'name' => $fileName,
            'size' => $size,
            'date_object' => new Zend_Date($date, Mage::app()->getLocale()->getLocaleCode())
        ));

        return $this;
    }

    /**
     * Delete log file
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function deleteFile()
    {
        if (!$this->exists()) {
            Mage::throwException(Mage::helper('tnw_salesforce')->__("Log file does not exist."));
        }

        $ioProxy = self::_createIoFile();
        $ioProxy->open(array('path' => $this->getPath()));
        $ioProxy->rm($this->getFileName());
        return $this;
    }

    /**
     * Checks log file exists.
     *
     * @return boolean
     */
    public function exists($fileName = null)
    {
        if ($fileName) {
            $exists = is_file($fileName);
        } else {
            $exists = is_file($this->getPath() . DS . $this->getFileName());
        }

        return $exists;
    }

    /**
     * Return file name of log file
     *
     * @return string
     */
    public function getFileName()
    {
        return $this->getName();
    }

    /**
     * @comment Prepare log filename
     * @param $file
     * @param $level
     * @return string
     * @throws Mage_Core_Exception
     */
    public function prepareFilename($file, $level)
    {
        if (!$file) {
            switch ($level) {
                case Zend_Log::ERR:
                    $file = 'sf-error';
                    break;
                case Zend_Log::CRIT:
                    $file = 'sf-email';
                    break;
                case Zend_Log::INFO:
                    $file = 'sf-info';
                    break;
                case Zend_Log::WARN:
                case Zend_Log::NOTICE:
                case Zend_Log::DEBUG:
                default:
                    $file = 'sf-trace';
                    break;
            }

            $file .= '-' . Mage::app()->getWebsite()->getId() . '-' . Mage::app()->getStore()->getId() . '.log';
        }

        return sprintf('%s%s%s',
            $this->getSalesforceLogDirName(),
            $this->checkSalesforceDir() ? DS : '-',
            $file
        );
    }

    /**
     * @return bool
     */
    protected function checkSalesforceDir()
    {
        static $checkWrite = null;
        if (is_null($checkWrite)) {
            $logDir  = Mage::getBaseDir('log') . DS . $this->getSalesforceLogDirName();
            if (!is_dir($logDir)) {
                mkdir($logDir);
                chmod($logDir, 0750);
            }

            $checkWrite = is_writable($logDir);
        }

        return $checkWrite;
    }

    /**
     * returns full filename
     * @param $file
     * @param $level
     * @return string
     */
    public function prepareFullFilename($file, $level = null) {

        return Mage::getBaseDir('log') . DS . $this->prepareFilename($file, $level = null);
    }

    /**
     * Write to log file
     *
     * @param $message
     * @param null $level
     * @param string $file
     * @return $this
     */
    public function write($message, $level = null, $file = '')
    {

        /**
         * @comment if detailed log is not enabled - write errors only in default log
         */
        if (
            !Mage::helper('tnw_salesforce/config')->isLogEnabled()
            && in_array($level, array(Zend_Log::CRIT, Zend_Log::ERR))
        ) {

            Mage::log($message);

            return false;
        }

        if ($level == Zend_Log::ERR) {

            /**
             * @comment add error message to the trace file too
             */
            $this->write($message, Zend_Log::DEBUG);

            /**
             * @comment save error for email send
             */
            $this->write($message, Zend_Log::CRIT);

            /**
             * Add config first time only
             */
            if (self::$saveConfig) {

                $message .= "\nSalesForce Config:\n";

                /**
                 * @comment add current configuration
                 */
                $configDump = Mage::helper('tnw_salesforce/config')->getConfigDump();

                $message .= var_export($configDump, true);

                self::$saveConfig = false;
            }
        }

        $file = $this->prepareFilename($file, $level);

        Mage::log($message, $level, $file);

        return $this;
    }

    /**
     * Print output
     */
    public function output()
    {
        echo $this->read(null)->getContent();
    }

    /**
     * read content
     * if $length is empty - try to load all file
     * @param $page
     * @param null $pageSize
     * @param int $whence
     * @return bool|string
     * @throws Exception
     */
    public function read($page, $pageSize = null, $whence = SEEK_SET)
    {
        if (!$this->exists()) {
            return false;
        }

        $isFull = is_null($page) || is_null($pageSize);
        if ($isFull) {
            $offset   = 0;
            $whence   = SEEK_SET;
        }
        else {
            $pageSize = max(intval($pageSize), 1) * 1024;
            $page     = max(intval($page), 1);
            $offset   = $page * $pageSize;
        }

        if ((false === $isFull) && ($offset - $pageSize > $this->getSize()) && ($page != 1)) {
            return $this->setContent('');
        }

        $ioAdapter = self::_createIoFile();
        $ioAdapter->open(array('path' => $this->getPath()));
        $ioAdapter->streamOpen($this->getFileName(), 'r');

        $ftellCurrent = 0;
        if (false === $isFull) {
            $ioAdapter->streamFseek($whence == SEEK_END ? -$offset : $offset, $whence);
            $ftellCurrent = $ioAdapter->streamFtell();

            // The last page is fully loaded
            if ((false === $isFull) && ($offset <= $this->getSize())) {
                $ioAdapter->streamRead();
            }
        }

        $content = '';
        while ($buffer = $ioAdapter->streamRead()) {
            $content .= $buffer;

            $ftell = $ioAdapter->streamFtell();
            if ($ftell > $ftellCurrent + $pageSize) {
               break;
            }
        }

        $ioAdapter->streamClose();
        return $this->setContent($content);
    }

    /**
     * @return int|mixed
     */
    public function getSize()
    {
        if (!is_null($this->getData('size'))) {
            return $this->getData('size');
        }

        if ($this->exists()) {
            $this->setData('size', filesize($this->getPath() . DS . $this->getFileName()));
            return $this->getData('size');
        }

        return 0;
    }

    /**
     * Load log by it's name
     *
     * @param $name
     * @return $this
     */
    public function loadByName($name)
    {
        $logCollection = Mage::getSingleton('tnw_salesforce/tool_log_file_collection');

        foreach ($logCollection as $log) {
            if ($log->getName() == $name) {

                $this
                    ->setTime($log->getTime())
                    ->setName($log->getName())
                    ->setPath($log->getPath());

                break;
            }
        }

        return $this;
    }
}
