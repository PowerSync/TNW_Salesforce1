<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Config_Customer_Lookup
{
    protected $_data = array();

    public function toOptionArray()
    {
        $this->_data['id_only'] = 'By Magento Id Only';

        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $this->_data['email_and_id'] = 'By Email and Magento Id';
        }

        return $this->_getOptions();
    }

    /**
     * @return array
     */
    protected function _getOptions() {
        $_result = array();
        foreach ($this->_data as $key => $value) {
            $_result[] = array(
                'value' => $key,
                'label' => $value,
            );
        }

        return $_result;
    }
}
