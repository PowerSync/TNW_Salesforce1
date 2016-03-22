<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

require_once 'Mage/Adminhtml/controllers/Sales/Order/StatusController.php';

class TNW_Salesforce_Adminhtml_Sales_Order_StatusController extends Mage_Adminhtml_Sales_Order_StatusController
{
    protected function _initStatus()
    {
        $statusCode = $this->getRequest()->getParam('status');
        if ($statusCode) {
            $status = Mage::getModel('sales/order_status')->load($statusCode);

            $collection = Mage::getModel('tnw_salesforce/order_status')->getCollection();
            $collection->getSelect()
                ->where("main_table.status = ?", $statusCode);
            foreach ($collection as $_item) {
                foreach (Mage::getModel('tnw_salesforce/config_order_status')->getAdditionalFields() as $_field) {
                    $status->setData($_field, $_item->{$_field});
                }
            }

        } else {
            $status = false;
        }

        return $status;
    }

    /**
     * Save status form processing
     */
    public function saveAction()
    {
        $data = $this->getRequest()->getPost();
        $isNew = $this->getRequest()->getParam('is_new');
        if ($data) {

            $statusCode = $this->getRequest()->getParam('status');

            //filter tags in labels/status
            /** @var $helper Mage_Adminhtml_Helper_Data */
            $helper = Mage::helper('adminhtml');
            if ($isNew) {
                $statusCode = $data['status'] = $helper->stripTags($data['status']);
            }
            $data['label'] = $helper->stripTags($data['label']);
            foreach ($data['store_labels'] as &$label) {
                $label = $helper->stripTags($label);
            }

            $status = Mage::getModel('sales/order_status')
                ->load($statusCode);

            // check if status exist
            if ($isNew && $status->getStatus()) {
                $this->_getSession()->addError(
                    Mage::helper('sales')->__('Order status with the same status code already exist.')
                );
                $this->_getSession()->setFormData($data);
                $this->_redirect('*/*/new');
                return;
            }

            $status->setData($data)
                ->setStatus($statusCode);
            try {
                $status->save();

                // TNW_Salesforce, added to save order status
                Mage::dispatchEvent('tnw_salesforce_order_status_save', array('status' => $statusCode, 'request' => $this->getRequest()));

                $this->_getSession()->addSuccess(Mage::helper('sales')->__('The order status has been saved.'));
                $this->_redirect('*/*/');
                return;
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addException(
                    $e,
                    Mage::helper('sales')->__('An error occurred while saving order status. The status has not been added.')
                );
            }
            $this->_getSession()->setFormData($data);
            if ($isNew) {
                $this->_redirect('*/*/new');
            } else {
                $this->_redirect('*/*/edit', array('status' => $this->getRequest()->getParam('status')));
            }
            return;
        }
        $this->_redirect('*/*/');
    }
}