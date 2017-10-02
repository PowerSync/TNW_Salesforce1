<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Sales_Order_View_Tab_Salesforce
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _prepareForm()
    {
        $orderWebsite = Mage::getSingleton('tnw_salesforce/localstorage')
            ->getWebsiteIdForType('sales/order', $this->getOrder()->getId());

        $form = new Varien_Data_Form(array(
            'id' => 'edit_form',
            'action' => $this->getUrl(
                '*/sales_order/saveSalesforce',
                array(
                    'order_id' => $this->getOrder()->getId(),
                )
            ),
            'method' => 'post',
            'enctype' => 'multipart/form-data'
        ));

        $form->setUseContainer(true);
        $form->setFieldNameSuffix('order');

        /** @var Varien_Data_Form_Element_Fieldset $fieldset */
        $fieldset = $form->addFieldset('fields', array(
            'legend' => Mage::helper('tnw_salesforce')->__('Salesforce')
        ));

        $fieldset->addType('salesforceId', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_salesforceId'));
        $fieldset->addType('owner', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_owner'));

        if (Mage::helper('tnw_salesforce/config_sales')->showOrderId()) {
            $fieldset->addField('salesforce_id', 'salesforceId', array(
                'label' => Mage::helper('tnw_salesforce')->__('Order'),
                'name' => 'salesforce_id',
                'website' => $orderWebsite
            ));
        }

        $fieldType = Mage::helper('tnw_salesforce/config_sales')->showOpportunityId()
            ? 'salesforceId' : 'text';
        $opportunityElement = $fieldset->addField('opportunity_id', $fieldType, array(
            'label' => $this->__('Opportunity'),
            'name' => 'opportunity_id',
        ));

        /**
         * check user ACL: can he update or define initial value of the Owner field
         */
        $isAllowed =
            (
                $this->getOrder()->getId() &&
                Mage::getSingleton('admin/session')
                    ->isAllowed('tnw_salesforce/edit_opportunity')
            ) ||
            (
                !$this->getOrder() &&
                Mage::getSingleton('admin/session')
                    ->isAllowed('tnw_salesforce/init_opportunity')
            );

        if (!$isAllowed) {
            $opportunityElement->setData('readonly', true);
        }

        $fieldset->addField('contact_salesforce_id', 'salesforceId', array(
            'label' => Mage::helper('tnw_salesforce')->__('Contact'),
            'name' => 'contact_salesforce_id',
            'website' => $orderWebsite
        ));

        $fieldset->addField('account_salesforce_id', 'salesforceId', array(
            'label' => Mage::helper('tnw_salesforce')->__('Account'),
            'name' => 'account_salesforce_id',
            'website' => $orderWebsite
        ));

        $ownerElement = $fieldset->addField('owner_salesforce_id', 'owner', array(
            'label' => Mage::helper('tnw_salesforce')->__('Owner'),
            'name' => 'owner_salesforce_id',
            'selector'  => 'tnw-ajax-find-select-owner',
            'website' => $orderWebsite
        ));

        if (!Mage::getSingleton('admin/session')->isAllowed('tnw_salesforce/edit_sales_owner')) {
            $ownerElement->setData('readonly', true);
        }

        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'label' => Mage::helper('tnw_salesforce')->__('Save Salesforce Data'),
                'onclick' => 'this.form.submit();',
                'class' => 'save'
            ));

        $fieldset->setHeaderBar($button->toHtml());

        $form->setValues($this->getOrder()->getData());
        $this->setForm($form);
    }

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
     * ######################## TAB settings #################################
     */
    public function getTabLabel()
    {
        return '<img height="20" src="'.$this->getJsUrl('tnw-salesforce/admin/images/sf-logo-small.png').'" class="tnw-salesforce-tab-icon"><label class="tnw-salesforce-tab-label">' . Mage::helper('tnw_salesforce')->__('Salesforce').'</label>';
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
