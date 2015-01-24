<?php

/**
 * Class TNW_Salesforce_Helper_Salesforce_Website
 */
class TNW_Salesforce_Helper_Salesforce_Website extends TNW_Salesforce_Helper_Salesforce_Abstract
{
    /**
     * @param bool $_return
     * @return bool|mixed
     */
    public function process()
    {
        if (!Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::helper('tnw_salesforce')->log("CRITICAL: Connection to Salesforce could not be established! Check API limits and/or login info.");
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addWarning('WARNING: SKIPPING synchronization, could not establish Salesforce connection.');
            }
            return false;
        }
        Mage::helper('tnw_salesforce')->log("================ MASS SYNC: START ================");
        if (!is_array($this->_cache) || empty($this->_cache['entitiesUpdating'])) {
            Mage::helper('tnw_salesforce')->log("WARNING: Sync websites, cache is empty!");
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: SKIPPING synchronization, could not locate website data to synchronize.');
            }
            return false;
        }

        try {
            // Prepare Data
            $this->_prepareWebsites();
            $this->clearMemory();

            // Push Data
            $this->_pushToSalesforce();
            $this->clearMemory();

            $this->_updateMagento();

            $this->_onComplete();

            Mage::helper('tnw_salesforce')->log("================= MASS SYNC: END =================");
        } catch (Eception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    protected function _onComplete()
    {
        if (Mage::helper('tnw_salesforce')->isRemoteLogEnabled()) {
            $logger = Mage::helper('tnw_salesforce/report');
            $logger->reset();

            $logger->add('Salesforce', $this->_prefix . 'Website__c', $this->_cache['websitesToUpsert'][$this->_prefix . 'Website_ID__c'], $this->_cache['responses']['websites']);

            $logger->send();
        }

        $this->reset();
        $this->clearMemory();
    }

    protected function _updateMagento()
    {
        $sql = "";
        foreach($this->_cache['toSaveInMagento'] as $_id => $_website) {
            if (
                is_object($_website)
                && property_exists($_website, 'salesforce_id')
                && $_website->salesforce_id
            ) {
                //Update Magento
                $sql .= "UPDATE `" . Mage::helper('tnw_salesforce')->getTable('core_website') . "` SET salesforce_id = '" . $_website->salesforce_id . "' WHERE website_id = " . $_id . ";";
            }
        }
        if (!empty($sql)) {
            Mage::helper('tnw_salesforce')->log("SQL: " . $sql);
            $this->_write->query($sql . ' commit;');
        }
    }

    protected function _pushToSalesforce()
    {
        // Website Sync
        Mage::helper('tnw_salesforce')->log("---------- Start: Website Sync ----------");
        $this->_dumpObjectToLog($this->_cache['websitesToUpsert'][$this->_prefix . 'Website_ID__c'], 'Website');

        $_websiteIds = array_keys($this->_cache['entitiesUpdating']);

        try {
            Mage::dispatchEvent("tnw_salesforce_website_send_before", array("data" => $this->_cache['websitesToUpsert'][$this->_prefix . 'Website_ID__c']));
            $_results = $this->_mySforceConnection->upsert(
                $this->_prefix . 'Website_ID__c',
                array_values($this->_cache['websitesToUpsert'][$this->_prefix . 'Website_ID__c']),
                $this->_prefix. 'Website__c'
            );
            Mage::dispatchEvent("tnw_salesforce_website_send_after",array(
                "data" => $this->_cache['websitesToUpsert'][$this->_prefix . 'Website_ID__c'],
                "result" => $_results
            ));
        } catch (Exception $e) {
            $_response = $this->_buildErrorResponse($e->getMessage());
            foreach($_websiteIds as $_id) {
                $this->_cache['responses']['websites'][$_id] = $_response;
            }
            $_results = array();
            Mage::helper('tnw_salesforce')->log('CRITICAL: Push of website to SalesForce failed' . $e->getMessage());
        }

        foreach ($_results as $_key => $_result) {
            //Report Transaction
            $this->_cache['responses']['websites'][$_websiteIds[$_key]] = $_result;

            if (property_exists($_result, 'success') && $_result->success) {
                $this->_cache['toSaveInMagento'][$_websiteIds[$_key]]->salesforce_id = $_result->id;
                Mage::helper('tnw_salesforce')->log("Websites: " . $_websiteIds[$_key] . " - ID: " . $_result->id);
            } else {
                $this->_processErrors($_result, 'website', $this->_cache['websitesToUpsert'][$_websiteIds[$_key]]);
            }
        }

        Mage::helper('tnw_salesforce')->log("Websites: " . implode(',', $_websiteIds) . " upserted!");
        Mage::helper('tnw_salesforce')->log("---------- End: Website Sync ----------");
    }

    protected function _prepareWebsites()
    {
        foreach($this->_cache['toSaveInMagento'] as $_id => $_website) {
            $_object = new stdClass();
            $_object->Name = $_website->name;
            $_object->{$this->_prefix . 'Website_ID__c'} = (int) $_website->website_id;
            $_object->{$this->_prefix . 'Code__c'} = $_website->code;
            $_object->{$this->_prefix . 'Sort_Order__c'} = (int) $_website->sort_order;

            if (!array_key_exists($this->_prefix . 'Website_ID__c', $this->_cache['websitesToUpsert'])) {
                $this->_cache['websitesToUpsert'][$this->_prefix . 'Website_ID__c'] = array();
            }
            $this->_cache['websitesToUpsert'][$this->_prefix . 'Website_ID__c'][$_id] = $_object;
        }
    }

    /**
     * @param array $ids
     */
    public function massAdd($ids = array())
    {
        try {
            $_websitesArray = array();

            foreach ($ids as $_id) {
                $_website = Mage::getModel('core/website')->load($_id);

                $tmp = new stdClass();
                foreach($_website->getData() as $_key => $_value) {
                    $tmp->$_key = $_website->getData($_key);
                }

                $this->_cache['toSaveInMagento'][$_website->getData('website_id')] = $tmp;

                $_websitesArray[$_website->getData('website_id')] = $_website->getData('salesforce_id');
            }
            $this->_cache['entitiesUpdating'] = $_websitesArray;
            $this->_cache['websitesLookup'] = Mage::helper('tnw_salesforce/salesforce_data_website')->websiteLookup($_websitesArray, array_keys($_websitesArray));

        } catch (Eception $e) {
            if (!$this->isFromCLI() && !$this->isCron() && Mage::helper('tnw_salesforce')->displayErrors()) {
                Mage::getSingleton('adminhtml/session')->addError('WARNING: ' . $e->getMessage());
            }
            Mage::helper("tnw_salesforce")->log("CRITICAL: " . $e->getMessage());
        }
    }

    public function reset()
    {
        parent::reset();

        $this->_cache = array(
            'websitesLookup' => array(),
            'websitesToUpsert' => array(),
            'entitiesUpdating' => array(),
            'toSaveInMagento' => array(),
            'responses' => array(
                'websites' => array()
            ),
        );

        return $this->check();
    }

    protected function _fillWebsiteSfIds(){
        $website = Mage::getModel('core/website')->load(0);
        $this->_websiteSfIds[0] = $website->getData('salesforce_id');
        foreach (Mage::app()->getWebsites() as $website) {
            $this->_websiteSfIds[$website->getData('website_id')] = $website->getData('salesforce_id');
        }
    }

}