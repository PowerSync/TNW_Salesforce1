<?php

class TNW_Salesforce_Block_Adminhtml_Sales_Order_Create_Salesforce extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     *
     */
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $form->setUseContainer(false);
        $form->setFieldNameSuffix('order');
        $form->addType('owner', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_owner'));

        $form->addField('owner_salesforce_id', 'owner', array(
            'name'      => 'owner_salesforce_id',
            'selector'  => 'tnw-ajax-find-select-owner-info',
            'value'     => $this->getSalesforceOwner()
        ));

        $this->setForm($form);
    }

    /**
     * @return string
     */
    protected function getSalesforceOwner()
    {
        $mapping  = Mage::getModel('tnw_salesforce/mapping_type_customer');
        $customer = $this->_getSession()->getQuote()->getCustomer();

        return $customer->getData('salesforce_id')
            ? $mapping->convertSalesforceContactOwnerId($customer)
            : $mapping->convertSalesforceLeadOwnerId($customer);
    }

    /**
     * Retrieve session object
     *
     * @return Mage_Adminhtml_Model_Session_Quote
     */
    protected function _getSession()
    {
        return Mage::getSingleton('adminhtml/session_quote');
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }
}