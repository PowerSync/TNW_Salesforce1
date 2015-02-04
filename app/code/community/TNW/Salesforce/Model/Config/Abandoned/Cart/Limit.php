<?php

/**
 * Created by PhpStorm.
 * User: evgeniy
 * Date: 05.02.15
 * Time: 0:42
 */
class TNW_Salesforce_Model_Config_Abandoned_Cart_Limit
{
    protected $_items = array();

    public function __construct()
    {
        $this->_items = array(
            array('value' => 1, 'label' => Mage::helper('adminhtml')->__('3 hours')),
            array('value' => 2, 'label' => Mage::helper('adminhtml')->__('6 hours')),
            array('value' => 3, 'label' => Mage::helper('adminhtml')->__('12 hours')),
            array('value' => 4, 'label' => Mage::helper('adminhtml')->__('1 day')),
            array('value' => 5, 'label' => Mage::helper('adminhtml')->__('3 days')),
            array('value' => 6, 'label' => Mage::helper('adminhtml')->__('1 week')),
            array('value' => 7, 'label' => Mage::helper('adminhtml')->__('2 weeks')),
            array('value' => 8, 'label' => Mage::helper('adminhtml')->__('1 month')),
        );
    }


    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array('value' => 1, 'label' => Mage::helper('adminhtml')->__('3 hours')),
            array('value' => 2, 'label' => Mage::helper('adminhtml')->__('6 hours')),
            array('value' => 3, 'label' => Mage::helper('adminhtml')->__('12 hours')),
            array('value' => 4, 'label' => Mage::helper('adminhtml')->__('1 day')),
            array('value' => 5, 'label' => Mage::helper('adminhtml')->__('3 days')),
            array('value' => 6, 'label' => Mage::helper('adminhtml')->__('1 week')),
            array('value' => 7, 'label' => Mage::helper('adminhtml')->__('2 weeks')),
            array('value' => 8, 'label' => Mage::helper('adminhtml')->__('1 month')),
        );
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        $_return = array();

        foreach($this->_items as $item) {
            $_return[$item['value']] = $item['label'];
        }
        return $_return;
    }

}