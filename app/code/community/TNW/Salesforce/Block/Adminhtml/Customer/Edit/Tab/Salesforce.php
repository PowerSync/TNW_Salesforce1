<?php

class TNW_Salesforce_Block_Adminhtml_Customer_Edit_Tab_Salesforce
    extends Mage_Adminhtml_Block_Template
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{

    /**
     * Return array of additional account data
     * Value is option style array
     *
     * @return array
     */
    public function getSalesforceData()
    {
        $_labels = array(
            'salesforce_lead_id'    =>  'Lead',
            'salesforce_id'         =>  'Contact',
            'salesforce_account_id' =>  'Account',
        );
        $sfData = array();
        foreach ($_labels as $_field => $_label) {
            $_value = Mage::registry('current_customer')->getData($_field);
            if ($_value) {
                $sfData[] = array(
                    'label' => $_label,
                    'value' => Mage::helper('tnw_salesforce/salesforce_abstract')->generateLinkToSalesforce($this->escapeHtml($_value, array('br')))
                );
            }
        }

        return $sfData;
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