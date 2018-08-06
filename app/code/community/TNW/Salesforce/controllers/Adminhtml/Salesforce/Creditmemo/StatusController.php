<?php

class TNW_Salesforce_Adminhtml_Salesforce_Creditmemo_StatusController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')
            ->isAllowed('tnw_salesforce/mappings/creditmemo_mapping/status_mapping');
    }

    /**
     * @return $this
     */
    protected function _initLayout()
    {
        $this->loadLayout()
            ->_setActiveMenu('system/salesforce')
            ->_addBreadcrumb($this->__('Credit Memo status Mapping'), $this->__('Credit Memo status Mapping'));

        return $this;
    }

    /**
     * Action
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))
            ->_title($this->__('Salesforce'))
            ->_title($this->__('Credit Memo status Mapping'));

        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_creditmemostatus'));

        $this->renderLayout();
    }

    /**
     * Action
     */
    public function newAction()
    {
        $this->_forward('edit');
    }

    public function editAction()
    {
        $statusId = $this->getRequest()->getParam('status_id');

        /** @var TNW_Salesforce_Model_Order_Creditmemo_Status $model */
        $model = Mage::getModel('tnw_salesforce/order_creditmemo_status')
            ->load($statusId);

        if (!$model->getId() && $statusId !== NULL) {
            Mage::getSingleton('adminhtml/session')->addError($this->__('Item does not exist'));
            $this->_redirect('*/*/');
            return;
        }

        $data = Mage::getSingleton('adminhtml/session')->getCreditMemoStatusMappingData(true);
        if (!empty($data)) {
            $model->setData($data);
        }

        Mage::register('credit_memo_status_mapping_data', $model);

        $this->_title($this->__('System'))
            ->_title($this->__('Salesforce'))
            ->_title($this->__('Credit Memo status Mapping'));

        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_creditmemostatus_edit'));

        $this->renderLayout();
    }

    /**
     * Save Action
     */
    public function saveAction()
    {
        $data = $this->getRequest()->getPost();
        if (empty($data)) {
            Mage::getSingleton('adminhtml/session')
                ->addError($this->__('Unable to find mapping to save'));

            $this->_redirect('*/*/');
            return;
        }

        $statusId = $this->getRequest()->getParam('status_id');
        /** @var TNW_Salesforce_Model_Order_Creditmemo_Status $model */
        $model = Mage::getModel('tnw_salesforce/order_creditmemo_status')
            ->load($statusId);

        $model->setData($data)
            ->setId($statusId);

        try {

            $select = $model
                ->getResource()
                ->getReadConnection()
                ->select()
                ->from($model->getResource()->getMainTable(), array('COUNT(*)'))
                ->where("(magento_stage = :magento_stage OR salesforce_status = :salesforce_status) AND status_id != :status_id")
            ;

            $bind = array(
                'magento_stage' => $model->getMagentoStage(),
                'salesforce_status' => $model->getSalesforceStatus(),
                'status_id' => (int)$model->getId(),
            );

            $isDuplicate = $model
                ->getResource()
                ->getReadConnection()
                ->fetchOne($select, $bind);

            if ($isDuplicate) {
                throw new Exception($this->__('Mapping with the same Magento Stage or Salesforce Status already exists.'));
            }


            $model->save();

            Mage::getSingleton('adminhtml/session')
                ->addSuccess($this->__('Mapping was successfully saved'))
                ->setCreditMemoStatusMappingData(false);

            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', array('status_id' => $model->getId()));
                return;
            }

            $this->_redirect('*/*/');
            return;
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')
                ->addError($e->getMessage())
                ->setCreditMemoStatusMappingData($data);

            $this->_redirect('*/*/edit', array('status_id' => $this->getRequest()->getParam('status_id')));
            return;
        }
    }

    public function massDeleteAction()
    {
        $statusIds = $this->getRequest()->getParam('status_ids');
        if (!is_array($statusIds)) {
            Mage::getSingleton('adminhtml/session')
                ->addError($this->__('Please select item(s)'));

            $this->_redirect('*/*/index');
            return;
        }

        try {
            /** @var TNW_Salesforce_Model_Order_Creditmemo_Status $model */
            $model = Mage::getModel('tnw_salesforce/order_creditmemo_status');
            foreach ($statusIds as $statusId) {
                $model->setId($statusId)->delete();
            }

            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Total of %d record(s) were successfully deleted', count($statusIds)));
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        }

        $this->_redirect('*/*/index');
    }
}