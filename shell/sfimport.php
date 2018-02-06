<?php

require_once 'abstract.php';

class Powersync_Shell_Sfimport extends Mage_Shell_Abstract
{
    const LOCK_INCOMING = 'incoming';
    const LOCK_OUTGOING = 'outgoing';
    const LOCK_BULK = 'bulk';

    /**
     * Run script
     *
     */
    public function run()
    {
        if (isset($this->_args['incoming'])) {
            try {
                $this->processLock(self::LOCK_INCOMING);
                Mage::getModel('tnw_salesforce/cron')->backgroundProcess();
                echo "Import successfully finished\n";
            } catch (Mage_Core_Exception $e) {
                echo $e->getMessage() . "\n";
            } catch (Exception $e) {
                echo "Compilation unknown error:\n\n";
                echo $e . "\n";
            }

            $this->processUnlock(self::LOCK_INCOMING);
        }
        else if (isset($this->_args['outgoing'])) {
            try {
                $websites = Mage::app()->getWebsites(true);
                if (isset($this->_args['website'])) {
                    $websites = array(Mage::app()->getWebsite($this->_args['website']));
                }

                $this->processLock(self::LOCK_OUTGOING);

                /** @var Mage_Core_Model_Website $website */
                foreach ($websites as $website) {
                    Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () {
                        Mage::getModel('tnw_salesforce/cron')->processQueue();
                    });
                }

                echo "Import successfully finished\n";
            } catch (Mage_Core_Exception $e) {
                echo $e->getMessage() . "\n";
            } catch (Exception $e) {
                echo "Compilation unknown error:\n\n";
                echo $e . "\n";
            }

            $this->processUnlock(self::LOCK_OUTGOING);
        }
        else if (isset($this->_args['bulk'])) {
            try {
                $websites = Mage::app()->getWebsites(true);
                if (isset($this->_args['website'])) {
                    $websites = array(Mage::app()->getWebsite($this->_args['website']));
                }

                $this->processLock(self::LOCK_BULK);

                /** @var Mage_Core_Model_Website $website */
                foreach ($websites as $website) {
                    Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function () {
                        Mage::getModel('tnw_salesforce/cron')->processBulkQueue();
                    });
                }

                echo "Import successfully finished\n";
            } catch (Mage_Core_Exception $e) {
                echo $e->getMessage() . "\n";
            } catch (Exception $e) {
                echo "Compilation unknown error:\n\n";
                echo $e . "\n";
            }

            $this->processUnlock(self::LOCK_BULK);
        }
        else {
            echo $this->usageHelp();
        }
    }

    /**
     * @param $name
     */
    protected function processLock($name)
    {
        if (!TNW_Salesforce_Model_Lock::getInstance()->setLock("tnw_{$name}", true)) {
            Mage::throwException(sprintf('The process "%s" blocked', $name));
        }
    }

    /**
     * @param $name
     */
    protected function processUnlock($name)
    {
        TNW_Salesforce_Model_Lock::getInstance()->releaseLock("tnw_{$name}", true);
    }

    /**
     * Retrieve Usage Help Message
     *
     */
    public function usageHelp()
    {
        return <<<USAGE
Usage:  php -f import.php -- [options]

  incoming      Run Incoming Queue Process
  outgoing      Run Outgoing Queue Process
  bulk          Run Bulk Queue Process
  help          This help

USAGE;
    }
}

$shell = new Powersync_Shell_Sfimport();
$shell->run();
