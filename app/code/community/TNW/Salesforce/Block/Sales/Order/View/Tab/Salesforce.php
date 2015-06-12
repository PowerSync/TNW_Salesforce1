<?php
class TNW_Salesforce_Block_Sales_Order_View_Tab_Salesforce
    extends Mage_Adminhtml_Block_Sales_Order_Abstract
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Retrieve order model instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    /**
     * Retrieve source model instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getSource()
    {
        return $this->getOrder();
    }

    public function getViewUrl($orderId)
    {
        return $this->getUrl('*/*/*', array('order_id'=>$orderId));
    }

    /**
     * ######################## TAB settings #################################
     */
    public function getTabLabel()
    {
        return '<img height="20" src="'.$this->getSkinUrl('images/sf-logo.png').'" style="display:inline-block;vertical-align:middle;"><caption style="display:inline-block;vertical-align:middle;">' . Mage::helper('tnw_salesforce')->__('Salesforce').'</caption>';
    }

    public function getTabTitle()
    {
        return Mage::helper('sales')->__('Salesforce');
    }

    public function canShowTab()
    {
        return true;
    }

    public function isHidden()
    {
        return false;
    }
}
