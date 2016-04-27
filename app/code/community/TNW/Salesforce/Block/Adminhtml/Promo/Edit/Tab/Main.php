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
        return true;
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

        $form = new Varien_Data_Form();

        $fieldset = $form->addFieldset('base_fieldset',
            array('legend' => $this->__('Salesforce'))
        );

        /** @var $campaignMemberCollection TNW_Salesforce_Model_Api_Entity_Resource_Campaign_Collection */
        $campaignMemberCollection = Mage::getResourceModel('tnw_salesforce_api_entity/campaign_collection')
            ->addFieldToFilter(
                array(TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . 'Magento_ID__c', 'Id'),
                array(array('eq' => null), array('eq' => $model->getData('salesforce_id')))
            );

        $fieldset->addField('assign_to_campaign', 'select', array(
            'name'      => 'assign_to_campaign',
            'label'     => $this->__('Assign to Campaign'),
            'title'     => $this->__('Assign to Campaign'),
            //'note'      => $this->__('Assign to Campaign'),
            'class'     => 'chosen-select',
            'options'   => array_merge(array(
                '0' => $this->__('Select Campaign')
            ), $campaignMemberCollection->toOptionHashCustom()),
            'value'     => $model->getData('salesforce_id')
        ));

        $this->setForm($form);
        return parent::_prepareForm();
    }

}