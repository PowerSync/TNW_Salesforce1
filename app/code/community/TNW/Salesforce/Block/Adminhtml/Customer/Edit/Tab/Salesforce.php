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

        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::registry('current_customer');
        $customerWebsite = Mage::getSingleton('tnw_salesforce/localstorage')
            ->getWebsiteIdForType('customer/customer', $customer->getId());

        $fieldset = $form->addFieldset('salesforce_fieldset', array(
            'legend' => Mage::helper('tnw_salesforce')->__('Salesforce')
        ));

        $fieldset->addType('salesforceId', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_salesforceId'));
        $fieldset->addField('salesforce_id', 'salesforceId', array(
            'label' => Mage::helper('tnw_salesforce')->__('Contact'),
            'name' => 'salesforce_id',
            'website' => $customerWebsite
        ));

        $fieldset->addField('salesforce_account_id', 'salesforceId', array(
            'label' => Mage::helper('tnw_salesforce')->__('Account'),
            'name' => 'salesforce_account_id',
            'website' => $customerWebsite
        ));

        $fieldset->addField('salesforce_lead_id', 'salesforceId', array(
            'label' => Mage::helper('tnw_salesforce')->__('Lead'),
            'name' => 'salesforce_lead_id',
            'website' => $customerWebsite
        ));

        $data = $customer->getData();
        if (!empty($data['salesforce_account_id'])) {
            $fieldset->addType('owner', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_owner'));
            $fieldset->addField('salesforce_account_owner_id', 'owner', array(
                'label' => Mage::helper('tnw_salesforce')->__('Account Owner'),
                'name' => 'salesforce_account_owner_id',
                'selector'  => 'tnw-ajax-find-select-account-owner',
                'website' => $customerWebsite
            ));
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