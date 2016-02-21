<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Order_Status
{
    protected $_additionalFields = array();

    public function getAdditionalFields() {
        if (!$this->_additionalFields) {
            $this->_setAdditionalFields();
        }
        return $this->_additionalFields;
    }

    protected function _setAdditionalFields() {
        $this->_additionalFields = array(
            'sf_lead_status_code',
            'sf_opportunity_status_code',
            'sf_order_status'
        );
    }

    public function toOptionArray()
    {
        $_statusArray = array();
        foreach(Mage::getModel('sales/order_status')->getResourceCollection()->getData() as $_status) {
            $_statusArray[] = array(
                'label' => $_status['label'],
                'value' => $_status['status']
            );
        }

        return $_statusArray;
    }

}
