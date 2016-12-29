<?php

require_once dirname(dirname(__FILE__)) . DIRECTORY_SEPARATOR . 'shell' . DIRECTORY_SEPARATOR . 'abstract.php';
class Powersync_Shell_Import extends Mage_Shell_Abstract
{
    const LOCK_INCOMING = 'incoming';
    const LOCK_OUTGOING = 'outgoing';
    const LOCK_BULK = 'bulk';

    /**
     * @var null
     */
    protected $_lockFile = array(
        self::LOCK_INCOMING => null,
        self::LOCK_OUTGOING => null,
        self::LOCK_BULK     => null,
    );

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

                /** @var Mage_Core_Model_App_Emulation $appEmulation */
                $appEmulation = Mage::getSingleton('core/app_emulation');

                /** @var Mage_Core_Model_Store[] $stores */
                $stores = array_map(function (Mage_Core_Model_Website $website) {
                    return $website->getDefaultStore();
                }, $websites);

                foreach ($stores as $store) {
                    $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($store->getId());
                    Mage::getModel('tnw_salesforce/cron')->processQueue();
                    $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
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

                /** @var Mage_Core_Model_App_Emulation $appEmulation */
                $appEmulation = Mage::getSingleton('core/app_emulation');

                /** @var Mage_Core_Model_Store[] $stores */
                $stores = array_map(function (Mage_Core_Model_Website $website) {
                    return $website->getDefaultStore();
                }, $websites);

                foreach ($stores as $store) {
                    $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($store->getId());
                    Mage::getModel('tnw_salesforce/cron')->processBulkQueue();
                    $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
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
        $file = Mage::getBaseDir('var') . DS . 'tnw_'.$name.'.lock';
        $this->_lockFile[$name] = fopen($file, 'w+');
        if (!flock($this->_lockFile[$name], LOCK_EX | LOCK_NB)) {
            @fclose($this->_lockFile[$name]);
            Mage::throwException(sprintf('The file "%s" blocked', $file));
        }
    }

    /**
     * @param $name
     */
    protected function processUnlock($name)
    {
        if (!is_resource($this->_lockFile[$name])) {
            return;
        }

        @flock($this->_lockFile[$name], LOCK_UN);
        @fclose($this->_lockFile[$name]);
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

$shell = new Powersync_Shell_Import();
$shell->run();
