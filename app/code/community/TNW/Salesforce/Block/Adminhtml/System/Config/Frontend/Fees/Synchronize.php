<?php

class TNW_Salesforce_Block_Adminhtml_System_Config_Frontend_Fees_Synchronize
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('salesforce/system/config/frontend/fees/synchronize.phtml');
    }

    /**
     * Enter description here...
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Generate synchronize button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'id'        => 'fees_synchronize_button',
                'label'     => $this->helper('adminhtml')->__('Create Fee Products in Salesforce'),
                'onclick'   => 'javascript:fees_synchronize(); return false;'
            ));

        return $button->toHtml();
    }
}