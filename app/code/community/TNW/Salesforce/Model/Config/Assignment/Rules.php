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
        $helper = Mage::helper('tnw_salesforce');

        $this->_ruleTypes = $helper->getStorage("tnw_salesforce_lead_assignment_rules");
        if (empty($this->_ruleTypes)) {
            $collection = Mage::helper('tnw_salesforce/salesforce_data')->getRules();
            if ($collection && count($collection) > 0) {
                foreach ($collection as $_item) {
                    $this->_rules[$_item->Id] = $_item->Name;
                }
                unset($collection);
                unset($_item);
            }

            $this->_ruleTypes = array();
            foreach ($this->_rules as $key => $_obj) {
                $this->_ruleTypes[] = array(
                    'label' => $_obj,
                    'value' => $key
                );
            }

            $helper->setStorage($this->_ruleTypes, 'tnw_salesforce_lead_assignment_rules');
        }

        return $this->_ruleTypes;
    }
}
