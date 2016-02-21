<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Assignment_Rules
{
    protected $_rules = array(0 => "Do not apply any assignment rules");
    protected $_ruleTypes = NULL;

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        $_useCache = Mage::app()->useCache('tnw_salesforce');
        $cache = Mage::app()->getCache();

        if ($_useCache && $cache->load("tnw_salesforce_lead_assignment_rules")) {
            $this->_ruleTypes = unserialize($cache->load("tnw_salesforce_lead_assignment_rules"));
        } else {
            $helper = Mage::helper('tnw_salesforce');
            if ($helper->isWorking() && $helper->isEnabled()) {
                $collection = Mage::helper('tnw_salesforce/salesforce_data')->getRules();
                if ($collection && count($collection) > 0) {
                    foreach ($collection as $_item) {
                        $this->_rules[$_item->Id] = $_item->Name;
                    }
                    unset($collection);
                    unset($_item);
                }
            }
            if (!$this->_ruleTypes) {
                $this->_ruleTypes = array();
                foreach ($this->_rules as $key => $_obj) {
                    $this->_ruleTypes[] = array(
                        'label' => $_obj,
                        'value' => $key
                    );
                }
            }

            if ($_useCache) {
                $cache->save(serialize($this->_ruleTypes), 'tnw_salesforce_lead_assignment_rules', array("TNW_SALESFORCE"));
            }
        }

        return $this->_ruleTypes;
    }
}
