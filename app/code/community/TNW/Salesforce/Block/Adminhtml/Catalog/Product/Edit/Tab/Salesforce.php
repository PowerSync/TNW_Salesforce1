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

        /** @var TNW_Salesforce_Block_Adminhtml_Catalog_Product_Renderer_SalesforceId $renderer */
        $renderer = Mage::getSingleton('core/layout')
            ->createBlock('tnw_salesforce/adminhtml_catalog_product_renderer_salesforceId');

        $fieldset
            ->addField('salesforce_id', 'text', array(
                'label'     => Mage::helper('tnw_salesforce')->__('Salesforce ID'),
                'name'      => 'salesforce_id',
            ))
            ->setRenderer($renderer);

        /** @var TNW_Salesforce_Block_Adminhtml_Catalog_Product_Renderer_Pricebooks $renderer */
        $renderer = Mage::getSingleton('core/layout')
            ->createBlock('tnw_salesforce/adminhtml_catalog_product_renderer_pricebooks');

        $fieldset
            ->addField('salesforce_pricebook_id', 'text', array(
                'label'     => Mage::helper('tnw_salesforce')->__('Salesforce Pricebook ID'),
                'name'      => 'salesforce_pricebook_id',
            ))
            ->setRenderer($renderer);

        /** @var TNW_Salesforce_Block_Adminhtml_Catalog_Product_Renderer_InSync $renderer */
        $renderer = Mage::getSingleton('core/layout')
            ->createBlock('tnw_salesforce/adminhtml_catalog_product_renderer_inSync');

        $fieldset
            ->addField('sf_insync', 'text', array(
                'label'     => Mage::helper('tnw_salesforce')->__('In Sync'),
                'name'      => 'sf_insync',
            ))
            ->setRenderer($renderer);

        $fieldset->addField('salesforce_disable_sync', 'select', array(
            'label'     => Mage::helper('tnw_salesforce')->__('Disable Synchronization'),
            'name'      => 'salesforce_disable_sync',
            'options'   => array(
                '1' => Mage::helper('checkout')->__('Yes'),
                '0' => Mage::helper('checkout')->__('No'),
            ),
        ));

        /** @var TNW_Salesforce_Block_Adminhtml_Catalog_Product_Renderer_Campaign $renderer */
        $renderer = Mage::getSingleton('core/layout')
            ->createBlock('tnw_salesforce/adminhtml_catalog_product_renderer_campaign');

        $fieldset
            ->addField('salesforce_campaign_id', 'text', array(
                'label'     => Mage::helper('tnw_salesforce')->__('Salesforce Campaign'),
                'name'      => 'salesforce_campaign_id',
            ))
            ->setRenderer($renderer);

        $form->setValues(Mage::registry('current_product')->getData());
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