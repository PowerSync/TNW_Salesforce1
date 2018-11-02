<?php

class TNW_Salesforce_Helper_Salesforce_Currency extends TNW_Salesforce_Helper_Salesforce_Abstract_Base
{
    /**
     * @param array $currencyCodes
     * @param bool|false $_isCron
     * @return bool
     */
    public function massAdd($currencyCodes = array(), $_isCron = false)
    {
        $this->cleanLastException();

        $this->_isCron  = $_isCron;

        $this->_skippedEntity = array();
        try {
            $this->_cache['currencyLookupAll'] = Mage::helper('tnw_salesforce/salesforce_data_currency')
                ->lookupAll();

            $currencyModel      = Mage::getModel('directory/currency');
            $defaultCurrencies  = $currencyModel->getConfigBaseCurrencies();
            $rates              = $currencyModel->getCurrencyRates($defaultCurrencies, $currencyCodes);

            list($_baseRateCode, $_baseRate) = each($rates);
            //$currencyModel = Mage::getResourceModel('directory/currency');
            foreach ($_baseRate as $_currencyCode => $_rate) {
                $obj = new stdClass();

                if (isset($this->_cache['currencyLookupAll'][$_currencyCode])) {
                    $obj->Id            = $this->_cache['currencyLookupAll'][$_currencyCode]->Id;
                    $obj->DecimalPlaces = $this->_cache['currencyLookupAll'][$_currencyCode]->DecimalPlaces;
                }
                else {
                    $obj->DecimalPlaces = 2;
                    $obj->IsoCode       = $_currencyCode;
                }

                $obj->IsCorporate       = ($_currencyCode == $_baseRateCode);
                $obj->ConversionRate    = floatval($_rate);
                $obj->IsActive          = true;

                $this->_cache['currencyToUpsert'][$_currencyCode] = $obj;
            }

            return !empty($this->_cache['currencyToUpsert']);
        } catch (Exception $e) {
            $this->lastException = $e;
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
        $this->cleanLastException();

        try {
            if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                throw new Exception('Connection to Salesforce could not be established! Check API limits and/or login info.');
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("================ REALTIME SYNC: START ================");

            if (!is_array($this->_cache) || empty($this->_cache['currencyToUpsert'])) {
                throw new Exception(sprintf('WARNING: Sync %s, cache is empty!', $this->getManyParentEntityType()));
            }

            $_keys = array_keys($this->_cache['currencyToUpsert']);
            try {
                $results = $this->getClient()->upsert('Id', array_values($this->_cache['currencyToUpsert']), 'CurrencyType');
            } catch (Exception $e) {
                $results   = array_fill(0, count($_keys),
                    $this->_buildErrorResponse($e->getMessage()));
            }

            foreach ($results as $_key => $_result) {
                $_entityNum = $_keys[$_key];

                //Report Transaction
                $this->_cache['responses']['currency'][$_entityNum] = $_result;

                if ($_result->success) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveTrace(sprintf('%s Upserted: %s' , 'CurrencyType', $_result->id));

                    continue;
                }

                $this->_processErrors($_result, 'CurrencyType', $this->_cache['currencyToUpsert'][$_entityNum]);
                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveError(sprintf('%s Failed: (%s: ' . $_entityNum . ')', 'CurrencyType', 'currency'));
            }

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("================= REALTIME SYNC: END =================");

            return true;
        }
        catch (Exception $e) {
            $this->lastException = $e;
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("CRITICAL: " . $e->getMessage());
            return false;
        }
    }

    /**
     * @return bool|void
     * Prepare values for the synchroization
     */
    public function reset()
    {
        parent::reset();
        return $this->check();
    }
}