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

        /** @var TNW_Salesforce_Block_Adminhtml_Catalog_Product_Renderer_SalesforceId $renderer */
        $renderer = Mage::getSingleton('core/layout')
            ->createBlock('tnw_salesforce/adminhtml_catalog_product_renderer_salesforceId');

        $fieldset
            ->addField('salesforce_id', 'text', array(
                'label' => Mage::helper('tnw_salesforce')->__('Order'),
                'name' => 'salesforce_id',
            ))
            ->setRenderer($renderer);

        $fieldset
            ->addField('contact_salesforce_id', 'text', array(
                'label' => Mage::helper('tnw_salesforce')->__('Contact'),
                'name' => 'contact_salesforce_id',
            ))
            ->setRenderer($renderer);

        $fieldset
            ->addField('account_salesforce_id', 'text', array(
                'label' => Mage::helper('tnw_salesforce')->__('Account'),
                'name' => 'account_salesforce_id',
            ))
            ->setRenderer($renderer);

        /** @var TNW_Salesforce_Block_Adminhtml_Widget_Form_Renderer_Fieldset_Owner $rendererOwner */
        $rendererOwner = Mage::getSingleton('core/layout')
            ->createBlock('tnw_salesforce/adminhtml_widget_form_renderer_fieldset_owner');

        $fieldset
            ->addField('owner_salesforce_id', 'text', array(
                'label' => Mage::helper('tnw_salesforce')->__('Owner'),
                'name' => 'owner_salesforce_id',
                'selector'  => 'tnw-ajax-find-select-owner'
            ))
            ->setRenderer($rendererOwner);

        if (Mage::helper('tnw_salesforce')->getOrderObject() != TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT) {
            $fieldset->addField('opportunity_id', 'text', array(
                'label' => $this->__('Opportunity ID'),
                'name' => 'opportunity_id',
            ));
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
