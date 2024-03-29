<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * Create order status form
 */
class TNW_Salesforce_Block_Sales_Order_Status_New_Form extends Mage_Adminhtml_Block_Sales_Order_Status_New_Form
{
    /**
     * Prepare form fields and structure
     *
     * @return Mage_Adminhtml_Block_Widget_Form
     */
    protected function _prepareForm()
    {
        parent::_prepareForm();

        $_form = $this->getForm();
        $_data = Mage::registry('current_status');

        Mage::dispatchEvent('tnw_salesforce_order_status_new_form', array('form' => $_form));

        if (is_object($_data)) {
            $_form->addValues($_data->getData());
        }
    }
}
