<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Sales_Order_View_Tab_Salesforce_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Retrieve order model instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    /**
     * @comment prepare form
     */
    protected function _prepareForm()
    {
        if (Mage::helper('tnw_salesforce')->getOrderObject() != TNW_Salesforce_Model_Config_Objects::OPPORTUNITY_OBJECT) {
            $form = new Varien_Data_Form(array(
                'id' => 'edit_form',
                'action' => $this->getUrl(
                        '*/sales_order/saveSalesforce',
                        array(
                            'order_id' => $this->getOrder()->getId(),
                        )
                    ),
                'method' => 'post',
                'enctype' => 'multipart/form-data'
            ));
            $form->setUseContainer(true);

            $fieldset = $form->addFieldset('fields', array('legend' => Mage::helper('tnw_salesforce')->__('Salesforce order data')));

            /**
             * Initialize product object as form property
             * for using it in elements generation
             */
            $form->setFieldNameSuffix('order');

            $fieldset->addField('opportunity_id', 'text', array(
                'label' => $this->__('Opportunity ID'),
                'name' => 'opportunity_id',
            ));

            $fieldset->addField('submit', 'note',
                array(
                    'text' => $this->getLayout()->createBlock('adminhtml/widget_button')
                            ->setData(array(
                                'label' => Mage::helper('tnw_salesforce')->__('Save'),
                                'onclick' => 'this.form.submit();',
                                'class' => 'save'
                            ))
                            ->toHtml(),
                )
            );


            $form->setValues($this->getOrder()->getData());


            $this->setForm($form);
        }
    }

}
