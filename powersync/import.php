<?php

require_once '../shell/abstract.php';
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
                $this->processLock(self::LOCK_OUTGOING);
                //Mage::getModel('tnw_salesforce/cron')->processQueue();
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
                $this->processLock(self::LOCK_BULK);
                Mage::getModel('tnw_salesforce/cron')->processBulkQueue();
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
