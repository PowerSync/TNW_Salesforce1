<?php

class TNW_Salesforce_Block_Adminhtml_Sales_Order_View_Salesforce extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $orderWebsite = Mage::getSingleton('tnw_salesforce/localstorage')
            ->getWebsiteIdForType('sales/order', $this->getOrder()->getId());

        $form = new Varien_Data_Form(array(
            'id' => 'salesforce_edit_form',
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
        $form->setFieldNameSuffix('order');

        $form->addType('owner', Mage::getConfig()->getBlockClassName('tnw_salesforce/adminhtml_widget_form_element_owner'));

        $ownerElement = $form->addField('owner_salesforce_id', 'owner', array(
            'name' => 'owner_salesforce_id',
            'selector' => 'tnw-ajax-find-select-owner-info',
            'website' => $orderWebsite
        ));

        if (!Mage::getSingleton('admin/session')->isAllowed('tnw_salesforce/edit_sales_owner')) {
            $ownerElement->setData('readonly', true);
        }

        $form->setValues($this->getOrder()->getData());
        $this->setForm($form);
    }

    /**
     * Retrieve order model instance
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        return Mage::registry('current_order');
    }

    public function getSaveButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData(array(
                'label' => Mage::helper('tnw_salesforce')->__('Save Salesforce Data'),
                'onclick' => "$('salesforce_edit_form').submit();",
                'class' => 'save'
            ));

        return $button->toHtml();
    }

    protected function _toHtml()
    {
        $orderWebsite = Mage::getSingleton('tnw_salesforce/localstorage')
            ->getWebsiteIdForType('sales/order', $this->getOrder()->getId());

        $isSkipped = Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($orderWebsite, function () {
            return !Mage::helper('tnw_salesforce')->isEnabled();
        });

        if ($isSkipped) {
            return '';
        }

        return parent::_toHtml();
    }
}