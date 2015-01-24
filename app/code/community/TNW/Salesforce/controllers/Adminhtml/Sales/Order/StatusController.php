<?php
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
                $status->setSfLeadStatusCode($_item->sf_lead_status_code);
                $status->setSfOpportunityStatusCode($_item->sf_opportunity_status_code);
                $status->setSfOrderStatus($_item->sf_order_status);
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

            $orderStatusMapping = Mage::getModel('tnw_salesforce/order_status');
            $collection = Mage::getModel('tnw_salesforce/order_status')->getCollection();
            $collection->getSelect()
                ->where("main_table.status = ?", $statusCode);
            foreach ($collection as $_item) {
                $orderStatusMapping->load($_item->status_id);
            }

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

                $orderStatusMapping->setStatus($statusCode)
                    ->setSfLeadStatusCode($this->getRequest()->getParam('sf_lead_status_code'))
                    ->setSfOpportunityStatusCode($this->getRequest()->getParam('sf_opportunity_status_code'))
                    ->setSfOrderStatus($this->getRequest()->getParam('sf_order_status'))
                    ->save();

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