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
        $html .= '<select name="' . $this->getElement()->getName() . '[account][]" ' . $this->_getDisabled() . '>';
        $html .= '<option value="">' . $this->__('* Select an Account') . '</option>';

        foreach ($this->getShippingMethods() as $_id => $_accountName) {
            $html .= '<option value="' . $this->escapeHtml($_id) . '" '
                . $this->_getSelected('account/' . $rowIndex, $_id)
                . ' style="background:white;">' . $this->escapeHtml($_accountName) . '</option>';
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
        $_useCache = Mage::app()->useCache('tnw_salesforce');
        $cache = Mage::app()->getCache();

        if ($this->hasData('salesforce_accounts')) {
            // Do nothing, just return
        }
        if ($cache->load("tnw_salesforce_accounts")) {
            $this->setData('salesforce_accounts', unserialize($cache->load("tnw_salesforce_accounts")));
        } else {
            $_allAccounts = array();
            if (Mage::helper('tnw_salesforce')->isWorking()) {
                $_client = Mage::getSingleton('tnw_salesforce/connection');
                if (!$_client->getServerUrl()) {
                    $_client->tryWsdl();
                    $_client->tryToConnect();
                    $_client->tryToLogin();

                    $instance_url = explode('/', $_client->getServerUrl());
                    Mage::getSingleton('core/session')->setSalesforceServerDomain('https://' . $instance_url[2]);
                    Mage::getSingleton('core/session')->setSalesforceSessionId($_client->getSessionId());
                }
                $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
                $manualSync->reset();
                $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
                $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));
                $_allAccounts = $manualSync->getAllAccounts();
            }

            if (!$this->hasData('salesforce_accounts')) {
                $this->setData('salesforce_accounts', $_allAccounts);
            }

            if ($_useCache && !empty($_allAccounts)) {
                $cache->save(serialize($this->getData('salesforce_accounts')), 'tnw_salesforce_accounts', array("TNW_SALESFORCE"));
            }
        }

        return $this->getData('salesforce_accounts');
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
                ->setOnClick("Element.insert($('" . $container . "'), {bottom: $('" . $template . "').innerHTML})")
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
