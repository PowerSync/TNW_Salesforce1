<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Model_Log
 */
class TNW_Salesforce_Model_Log extends Varien_Object
{
    /**
     * experimental feature
     */
    const AUTOMATE_FLUSH_MESSAGE_ALLOWED = false;

    /**
     * @var null
     */
    protected $_logDir = null;

    protected $_salesforceLogDirName = 'salesforce';

    /**
     * @comment module log dirrectory
     * @return string
     */
    public function getLogDir()
    {
        if (!$this->_logDir) {
            $this->_logDir = Mage::getBaseDir('log') . DS . $this->getSalesforceLogDirName();
            // check for valid base dir
            $ioProxy = new Varien_Io_File();
            $ioProxy->mkdir($this->_baseDir);
        }
        return $this->_logDir;
    }

    public function getSalesforceLogDirName()
    {
        return $this->_salesforceLogDirName;
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
     * @throws Mage_Log_Exception
     * @return Mage_Log_Model_Log
     */
    public function deleteFile()
    {
        if (!$this->exists()) {
            Mage::throwException(Mage::helper('tnw_salesforce')->__("Log file does not exist."));
        }

        $ioProxy = new Varien_Io_File();
        $ioProxy->open(array('path' => $this->getPath()));
        $ioProxy->rm($this->getFileName());
        return $this;
    }

    /**
     * Checks log file exists.
     *
     * @return boolean
     */
    public function exists()
    {
        return is_file($this->getPath() . DS . $this->getFileName());
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
     * @comment send Email with error message
     */
    public function send()
    {
        if (Mage::helper('tnw_salesforce/config')->getFailEmail()) {
            $filename = Mage::getBaseDir('log') . DS . $this->prepareFilename(null, Zend_Log::CRIT);

            if (!file_exists($filename) || filesize($filename) == 0) {
                return false;
            }


            $_storeName = Mage::getStoreConfig('general/store_information/name');
            # Cannot connect to SF, execute email


            $mail = new Zend_Mail();
            $body = "<p><b>Alert:</b> " . $_storeName . " experienced the following problem while trying to post data to SalesForce.com</p>";
            $body .= "<p><b>Error:</b> Unable to push the request</p>";
            $body .= "<p><b>Record Information:</b><br /><br />";

            $body .= "</p>";
            $body .= "<p><b>SalesForce Error:</b><br/>";
            $body .= file_get_contents($filename);
            $body .= "</p>";

            $body .= "<p>To incorporate this information into SalesForce.com you can key in the data referenced above.</p>";
            $body .= "<p>If you have any questions, please contact the support staff of " . $_storeName . ".</p>";

            $mail->setBodyHtml($body);
            unset($body);
            $mail->setFrom(Mage::getStoreConfig('trans_email/ident_general/email'), Mage::getStoreConfig('trans_email/ident_general/name'));
            $emails = explode(",", Mage::helper('tnw_salesforce/config')->getFailEmail());
            foreach ($emails as $email) {
                $mail->addTo($email, $email);
            }
            unset($emails, $email);
            $subject = "";
            if (Mage::helper('tnw_salesforce/config')->getFailEmailPrefix()) {
                $subject = Mage::helper('tnw_salesforce/config')->getFailEmailPrefix() . " - ";
            }
            $subject .= "Unable to push update from " . $_storeName . " into SalesForce";
            if ($mail->getSubject() !== null) {
                $mail->clearSubject();
            }
            $mail->setSubject($subject);

            try {
                $mail->send();
                $ioAdapter = new Varien_Io_File();
                $ioAdapter->rm($filename);

            } catch (Exception $e) {
                $this->write(
                    sprintf('Could not send an email containing the error. Error from email: %s', $e->getMessage()),
                    Zend_Log::ERR
                );
            }
        }
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
                case Zend_Log::NOTICE:
                    $file = 'sf-notice';
                    break;
                case Zend_Log::WARN:
                    $file = 'sf-warning';
                    break;
                case Zend_Log::CRIT:
                    $file = 'sf-email';
                    break;
                case Zend_Log::DEBUG:
                default:
                    $file = 'sf-trace';
                    break;
            }
        }
        $file = $this->getSalesforceLogDirName() . DS . $file;
        $file .= '-' . Mage::app()->getWebsite()->getId() . '-' . Mage::app()->getStore()->getId() . '.log';

        return $file;
    }

    /**
     * returns full filename
     * @param $file
     * @param $level
     * @return string
     */
    public function prepareFullFilename($file, $level) {

        return Mage::getBaseDir('log') . DS . $this->prepareFilename($file, $level);
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
         * @comment if detailed log is not enabled - write errors only
         */
        if (
            !Mage::helper('tnw_salesforce/config')->isLogEnabled()
            && !in_array($level, array(Zend_Log::CRIT, Zend_Log::ERR))
        ) {
            return false;
        }

        /**
         * @comment add session message is we in Admin area
         */
        if (
            self::AUTOMATE_FLUSH_MESSAGE_ALLOWED
            && Mage::app()->getStore()->isAdmin()
            && PHP_SAPI != 'cli'
        ) {

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


        if ($level == Zend_Log::ERR) {
            /**
             * @comment add error message to the trace file too
             */
            $this->write($message, Zend_Log::DEBUG);

            /**
             * @comment add current configuration
             */
            $configDump = Mage::helper('tnw_salesforce/config')->getConfigDump();

            $message .= var_export($configDump, true);

            /**
             * @comment save error for email send
             */
            $this->write($message, Zend_Log::CRIT);
        }

        $file = $this->prepareFilename($file, $level);

        Mage::log($message, $level, $file);

        return $this;
    }

    /**
     * Print output
     *
     */
    public function output()
    {
        if (!$this->exists()) {
            return;
        }

        $ioAdapter = new Varien_Io_File();
        $ioAdapter->open(array('path' => $this->getPath()));

        $ioAdapter->streamOpen($this->getFileName(), 'r');
        while ($buffer = $ioAdapter->streamRead()) {
            echo $buffer;
        }
        $ioAdapter->streamClose();
    }

    /**
     * read content
     */
    public function read()
    {
        if (!$this->exists()) {
            return;
        }

        $ioAdapter = new Varien_Io_File();
        $ioAdapter->open(array('path' => $this->getPath()));

        $ioAdapter->streamOpen($this->getFileName(), 'r');
        $content = '';

        while ($buffer = $ioAdapter->streamRead()) {
            $content .= $buffer;
        }
        $ioAdapter->streamClose();

        $this->setContent($content);

        return $this;
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
     * @param int $timestamp
     * @return Mage_Log_Model_Log
     */
    public function loadByName($name)
    {
        $logCollection = Mage::getSingleton('tnw_salesforce/log_collection');

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
