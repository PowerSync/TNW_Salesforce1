<?php

class TNW_Salesforce_Helper_Salesforce_Currency extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @param array $_ids
     * @param bool|false $_isCron
     * @return bool
     */
    public function massAdd($_ids = array(), $_isCron = false)
    {
        $this->_isCron  = $_isCron;

        // test sf api connection
        /** @var TNW_Salesforce_Model_Connection $_client */
        $_client = Mage::getSingleton('tnw_salesforce/connection');
        if (!$_client->initConnection()) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR on sync entity, sf api connection failed");

            return false;
        }

        $this->_skippedEntity = array();
        try {
            $currencyModel = Mage::getModel('directory/currency');
            $currencies = $currencyModel->getConfigAllowCurrencies();
            $defaultCurrencies = $currencyModel->getConfigBaseCurrencies();

            $currencyModel->getCurrencyRates($defaultCurrencies, $currencies);

            foreach ($defaultCurrencies as $_currencyCode) {

                // Associate order ID with order Number
                //$this->_cache[self::CACHE_KEY_ENTITIES_UPDATING][$_id] = $_entityNumber;
            }

            return !empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING]);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @param string $type
     * @return bool
     */
    public function process($type = 'soft')
    {
        try {
            if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
                if (!$this->isFromCLI() && Mage::helper('tnw_salesforce')->displayErrors()) {
                    Mage::getSingleton('adminhtml/session')->addWarning('WARNING: SKIPPING synchronization, could not establish Salesforce connection.');
                }
                return false;
            }

            $_syncType = stripos(get_class($this), '_bulk_') !== false ? 'MASS' : 'REALTIME';
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("================ %s SYNC: START ================", $_syncType));

            if (!is_array($this->_cache) || empty($this->_cache[self::CACHE_KEY_ENTITIES_UPDATING])) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError(sprintf("WARNING: Sync %s, cache is empty!", $this->getManyParentEntityType()));
                $this->_dumpObjectToLog($this->_cache, "Cache", true);
                return false;
            }



            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace(sprintf("================= %s SYNC: END =================", $_syncType));
            return true;
        }
        catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }
}