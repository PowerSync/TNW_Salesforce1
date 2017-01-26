<?php

class TNW_Salesforce_Block_Adminhtml_Promo_Edit_Tab_Main
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Prepare content for tab
     *
     * @return string
     */
    public function getTabLabel()
    {
        return $this->__('Salesforce');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        return $this->__('Salesforce');
    }

    /**
     * Returns status flag about this tab can be showed or not
     *
     * @return true
     */
    public function canShowTab()
    {
        return Mage::helper('tnw_salesforce')->isOrderRulesEnabled();
    }

    /**
     * Returns status flag about this tab hidden or not
     *
     * @return true
     */
    public function isHidden()
    {
        return false;
    }

    protected function _prepareForm()
    {
        /** @var Mage_SalesRule_Model_Rule $model */
        $model = Mage::registry('current_promo_quote_rule');

        $ruleWebsite = Mage::getSingleton('tnw_salesforce/localstorage')
            ->getWebsiteIdForType('salesrule/rule', $model->getId());

        $form = new Varien_Data_Form();
        $fieldset = $form->addFieldset('base_fieldset',
            array('legend' => $this->__('Salesforce'))
        );

        $fieldset->addType('campaign', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_campaign'));
        $fieldset->addField('assign_to_campaign', 'campaign', array(
            'label'     => $this->__('Assign to Campaign'),
            'title'     => $this->__('Assign to Campaign'),
            'name'      => 'assign_to_campaign',
            'selector'  => 'tnw-ajax-find-select',
            'value'     => $model->getData('salesforce_id'),
            'website'   => $ruleWebsite
        ));

        $this->setForm($form);
        return parent::_prepareForm();
    }

}