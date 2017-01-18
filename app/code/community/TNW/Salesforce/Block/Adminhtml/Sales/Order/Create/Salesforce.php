<?php

class TNW_Salesforce_Block_Adminhtml_Sales_Order_Create_Salesforce extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     *
     */
    protected function _prepareForm()
    {
        $orderWebsite = Mage::getSingleton('tnw_salesforce/localstorage')
            ->getWebsiteIdForType('sales/quote', $this->_getSession()->getQuote()->getId());

        $form = new Varien_Data_Form();
        $form->setUseContainer(false);
        $form->setFieldNameSuffix('order');
        $form->addType('owner', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_owner'));

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $this->_getSession()->getQuote()->getCustomer();
        $salesforceOwner = Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($orderWebsite, function () use($customer) {
            $mapping  = Mage::getModel('tnw_salesforce/mapping_type_customer');

            return $customer->getData('salesforce_id')
                ? $mapping->convertSalesforceContactOwnerId($customer)
                : $mapping->convertSalesforceLeadOwnerId($customer);
        });

        $form->addField('owner_salesforce_id', 'owner', array(
            'name'      => 'owner_salesforce_id',
            'selector'  => 'tnw-ajax-find-select-owner-info',
            'value'     => $this->getSalesforceOwner(),
            'website'   => $orderWebsite
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