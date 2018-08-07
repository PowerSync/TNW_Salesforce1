<?php

class TNW_Salesforce_Block_Adminhtml_Sales_Order_Create_Salesforce extends Mage_Adminhtml_Block_Widget_Form
{

    /**
     * @return bool
     */
    protected function isNew()
    {
        return true;
    }

    /**
     *
     */
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $form->setUseContainer(false);
        $form->setFieldNameSuffix('order');
        $form->addType('owner', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_owner'));

        $ownerElement = $form->addField('owner_salesforce_id', 'owner', array(
            'name'      => 'owner_salesforce_id',
            'selector'  => 'tnw-ajax-find-select-owner-info',
            'value'     => $this->getSalesforceOwner(),
            'website'   => $this->getQuoteWebsiteId()
        ));

        /**
         * check user ACL: can he update or define initial value of the Owner field
         */
        $isAllowed =
            (
                !$this->isNew() &&
                Mage::getSingleton('admin/session')
                    ->isAllowed('tnw_salesforce/edit_sales_owner')
            ) ||
            (
                $this->isNew() &&
                Mage::getSingleton('admin/session')
                    ->isAllowed('tnw_salesforce/init_sales_owner')
            )
        ;

        if (!$isAllowed) {
            $ownerElement->setData('readonly', true);
        }

        $this->setForm($form);
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
     * @return int
     */
    protected function getQuoteWebsiteId()
    {
        return Mage::getSingleton('tnw_salesforce/localstorage')
            ->getWebsiteIdForType('sales/quote', $this->_getSession()->getQuote()->getId());
    }

    /**
     * @return string
     */
    public function getSalesforceOwner()
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = $this->_getSession()->getQuote()->getCustomer();
        return Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($this->getQuoteWebsiteId(), function () use($customer) {
            /** @var tnw_salesforce_model_mysql4_mapping_collection $_mappingCollection */
            $mappingCollection = Mage::getResourceModel('tnw_salesforce/mapping_collection')
                ->addObjectToFilter('Order')
                ->addFieldToFilter('sf_field', 'OwnerId')
                ->firstSystem();

            /** @var tnw_salesforce_model_mapping $mapping */
            $mapping = $mappingCollection->getFirstItem();

            $objectMappings['Order'] = Mage::getModel('sales/order');
            $objectMappings['Customer'] = $customer;

            return $mapping->getValue($objectMappings);
        });
    }

    /**
     * @return string
     */
    protected function _toHtml()
    {
        $isEnable = Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($this->getQuoteWebsiteId(), function () {
            return Mage::helper('tnw_salesforce')->isEnabled();
        });

        if (!$isEnable) {
            return '';
        }

        return parent::_toHtml();
    }
}