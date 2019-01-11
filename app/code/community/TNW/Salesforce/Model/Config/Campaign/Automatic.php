<?php

class TNW_Salesforce_Model_Config_Campaign_Automatic extends Mage_Adminhtml_Model_System_Config_Source_Yesno
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 1, 'label'=>Mage::helper('adminhtml')->__('Create automatically')),
            array('value' => 0, 'label'=>Mage::helper('adminhtml')->__('Create manually')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            1 => Mage::helper('adminhtml')->__('Create automatically'),
            0 => Mage::helper('adminhtml')->__('Create manually'),
        );
    }
}