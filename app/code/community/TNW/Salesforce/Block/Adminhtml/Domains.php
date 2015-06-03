<?php

class TNW_Salesforce_Block_Adminhtml_Domains
    extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected $_addRowButtonHtml = array();
    protected $_removeRowButtonHtml = array();

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $html = '<div id="salesforce_catchall_account_template" style="display:none">';
        $html .= $this->_getRowTemplateHtml();
        $html .= '</div>';

        $html .= '<ul id="salesforce_catchall_account_container">';
        if ($this->_getValue('account')) {
            foreach ($this->_getValue('account') as $i => $f) {
                if ($i) {
                    $html .= $this->_getRowTemplateHtml($i);
                }
            }
        }
        $html .= '</ul>';
        $html .= $this->_getAddRowButtonHtml('salesforce_catchall_account_container',
            'salesforce_catchall_account_template', $this->__('Add Email Domain'));

        return $html;
    }

    /**
     * Retrieve html template for shipping method row
     *
     * @param int $rowIndex
     * @return string
     */
    protected function _getRowTemplateHtml($rowIndex = 0)
    {
        $html = '<li>';
        $html .= '<select class="chosen-select" name="' . $this->getElement()->getName() . '[account][]" ' . $this->_getDisabled() . '>';
        $html .= '<option value="">' . $this->__('* Select an Account') . '</option>';

        foreach ($this->getShippingMethods() as $_id => $_accountName) {
            $html .= '<option value="' . $this->escapeHtml($_id) . '" '
                . $this->_getSelected('account/' . $rowIndex, $_id)
                . '>' . $this->escapeHtml($_accountName) . '</option>';
        }
        $html .= '</select>';

        $html .= '<div style="margin:5px 0 10px;">';
        $html .= '<label>' . $this->__('Email Domain:') . '</label> ';
        $html .= '<input class="input-text" style="width:140px;" name="'
            . $this->getElement()->getName() . '[domain][]" value="'
            . $this->_getValue('domain/' . $rowIndex) . '" ' . $this->_getDisabled() . '/> ';

        $html .= $this->_getRemoveRowButtonHtml();
        $html .= '</div>';
        $html .= '</li>';

        return $html;
    }

    protected function getShippingMethods()
    {
        return $this->helper('tnw_salesforce/config')->getSalesforceAccounts();
    }

    protected function _getDisabled()
    {
        return $this->getElement()->getDisabled() ? ' disabled' : '';
    }

    protected function _getValue($key)
    {
        return $this->getElement()->getData('value/' . $key);
    }

    protected function _getSelected($key, $value)
    {
        return $this->getElement()->getData('value/' . $key) == $value ? 'selected="selected"' : '';
    }

    protected function _getAddRowButtonHtml($container, $template, $title = 'Add')
    {
        if (!isset($this->_addRowButtonHtml[$container])) {
            $this->_addRowButtonHtml[$container] = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setType('button')
                ->setClass('add ' . $this->_getDisabled())
                ->setLabel($this->__($title))
                ->setOnClick("$('" . $container . "').insert({bottom: $('" . $template . "').innerHTML}); new Chosen($('" . $container . "').select('select').last());")
                ->setDisabled($this->_getDisabled())
                ->toHtml();
        }
        return $this->_addRowButtonHtml[$container];
    }

    protected function _getRemoveRowButtonHtml($selector = 'li', $title = 'Remove')
    {
        if (!$this->_removeRowButtonHtml) {
            $this->_removeRowButtonHtml = $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setType('button')
                ->setClass('delete v-middle ' . $this->_getDisabled())
                ->setLabel($this->__($title))
                ->setOnClick("Element.remove($(this).up('" . $selector . "'))")
                ->setDisabled($this->_getDisabled())
                ->toHtml();
        }
        return $this->_removeRowButtonHtml;
    }
}
