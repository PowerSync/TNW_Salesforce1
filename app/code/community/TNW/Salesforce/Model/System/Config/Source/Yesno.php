<?php

/**
 * Used in creating options for Yes|No config value selection
 *
 */
class TNW_Salesforce_Model_System_Config_Source_Yesno extends Mage_Adminhtml_Model_System_Config_Source_Yesno
{

    /**
     * Get options in "key-value" format
     * Method is necessary for compatibility with old Magento versions
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            0 => Mage::helper('adminhtml')->__('No'),
            1 => Mage::helper('adminhtml')->__('Yes'),
        );
    }

}
