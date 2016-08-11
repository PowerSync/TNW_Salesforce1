<?php

class TNW_Salesforce_Block_Adminhtml_Catalog_Product_Edit_Tab_Salesforce
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $form->setUseContainer(false);
        $form->setFieldNameSuffix('product');

        $fieldset = $form->addFieldset('salesforce_fieldset', array(
            'legend'    => Mage::helper('tnw_salesforce')->__('Salesforce')
        ));

        $fieldset->addField('salesforce_id', 'text', array(
            'label' => Mage::helper('tnw_salesforce')->__('Salesforce ID'),
            'name'  => 'salesforce_id',
        ));

        $fieldset->addField('salesforce_pricebook_id', 'text', array(
            'label' => Mage::helper('tnw_salesforce')->__('Salesforce Pricebook ID'),
            'name'  => 'salesforce_pricebook_id',
        ));

        $fieldset->addField('in_sync', 'text', array(
            'label' => Mage::helper('tnw_salesforce')->__('In Sync'),
            'name'  => 'in_sync',
        ));

        $fieldset->addField('salesforce_disable_sync', 'text', array(
            'label' => Mage::helper('tnw_salesforce')->__('Disable Synchronization'),
            'name'  => 'salesforce_disable_sync',
        ));

        $fieldset->addField('salesforce_campaign_id', 'text', array(
            'label' => Mage::helper('tnw_salesforce')->__('Salesforce Campaign'),
            'name'  => 'salesforce_campaign_id',
        ));

        $this->setForm($form);
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return Mage::helper('tnw_salesforce')->__('Salesforce');
    }

    /**
     * Return Tab title
     *
     * @return string
     */
    public function getTabTitle()
    {
        return Mage::helper('tnw_salesforce')->__('Salesforce');
    }

    /**
     * Can show tab in tabs
     *
     * @return boolean
     */
    public function canShowTab()
    {
        return true;
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        return false;
    }
}