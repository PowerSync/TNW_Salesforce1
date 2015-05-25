<?php

/**
 * Class TNW_Salesforce_Block_Adminhtml_Check
 */
class TNW_Salesforce_Block_Adminhtml_Check extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'tnw_salesforce';
        $this->_controller = 'adminhtml_check';
        $this->_headerText = $this->__('Integration Check');
        parent::__construct();
        $this->_buttons = array();
    }

    /**
     * Do not prepare layout if cannot show
     *
     * @return TNW_Salesforce_Block_Adminhtml_Check
     */
    protected function _prepareLayout()
    {
        return $this->canShow() ? parent::_prepareLayout() : $this;
    }

    /**
     * Do not render html if cannot show
     *
     * @return string
     */
    protected function _toHtml()
    {
        if (!$this->canShow()) {
            return '';
        }

        return parent::_toHtml();
    }

    /**
     * @return bool
     */
    protected function isSalesforceSection()
    {
        return $this->helper('tnw_salesforce')->isApiConfigurationPage();
    }

    /**
     * @return bool
     */
    public function canShow()
    {
        return $this->isSalesforceSection() && Mage::helper('tnw_salesforce')->isEnabled();
    }
}
