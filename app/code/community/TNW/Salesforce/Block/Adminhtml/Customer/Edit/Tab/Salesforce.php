<?php

class TNW_Salesforce_Block_Adminhtml_Customer_Edit_Tab_Salesforce
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $form->setUseContainer(false);
        $form->setFieldNameSuffix('account');

        $data = Mage::registry('current_customer')->getData();

        $fieldset = $form->addFieldset('salesforce_fieldset', array(
            'legend' => Mage::helper('tnw_salesforce')->__('Salesforce')
        ));

        /** @var TNW_Salesforce_Block_Adminhtml_Catalog_Product_Renderer_SalesforceId $renderer */
        $renderer = Mage::getSingleton('core/layout')
            ->createBlock('tnw_salesforce/adminhtml_catalog_product_renderer_salesforceId');

        $fieldset
            ->addField('salesforce_id', 'text', array(
                'label' => Mage::helper('tnw_salesforce')->__('Contact'),
                'name' => 'salesforce_id',
            ))
            ->setRenderer($renderer);

        $fieldset
            ->addField('salesforce_account_id', 'text', array(
                'label' => Mage::helper('tnw_salesforce')->__('Account'),
                'name' => 'salesforce_account_id',
            ))
            ->setRenderer($renderer);

        $fieldset
            ->addField('salesforce_lead_id', 'text', array(
                'label' => Mage::helper('tnw_salesforce')->__('Lead'),
                'name' => 'salesforce_lead_id',
            ))
            ->setRenderer($renderer);

        /** @var TNW_Salesforce_Block_Adminhtml_Widget_Form_Renderer_Fieldset_Owner $rendererOwner */
        $rendererOwner = Mage::getSingleton('core/layout')
            ->createBlock('tnw_salesforce/adminhtml_widget_form_renderer_fieldset_owner');

        if (!empty($data['salesforce_account_owner_id']) && !empty($data['salesforce_account_id'])) {
            $fieldset
                ->addField('salesforce_account_owner_id', 'text', array(
                    'label' => Mage::helper('tnw_salesforce')->__('Account Owner'),
                    'name' => 'salesforce_account_owner_id',
                    'selector'  => 'tnw-ajax-find-select-account-owner'
                ))
                ->setRenderer($rendererOwner);
        }

        if (!empty($data['salesforce_lead_owner_id']) && !empty($data['salesforce_lead_id'])) {
            $fieldset
                    ->addField('salesforce_lead_owner_id', 'text', array(
                    'label'     => Mage::helper('tnw_salesforce')->__('Lead Owner'),
                    'name'      => 'salesforce_lead_owner_id',
                    'selector'  => 'tnw-ajax-find-select-lead-owner'
                ))
                ->setRenderer($rendererOwner);
        }

        $form->setValues($data);
        $this->setForm($form);
    }

    /**
     * Return Tab label
     *
     * @return string
     */
    public function getTabLabel()
    {
        return '<img height="20" src="'.$this->getJsUrl('tnw-salesforce/admin/images/sf-logo-small.png').'" class="tnw-salesforce-tab-icon"><label class="tnw-salesforce-tab-label">' . Mage::helper('tnw_salesforce')->__('Salesforce').'</label>';
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
        if (Mage::registry('current_customer')->getId()) {
            return true;
        }
        return false;
    }

    /**
     * Tab is hidden
     *
     * @return boolean
     */
    public function isHidden()
    {
        if (Mage::registry('current_customer')->getId()) {
            return false;
        }
        return true;
    }
}