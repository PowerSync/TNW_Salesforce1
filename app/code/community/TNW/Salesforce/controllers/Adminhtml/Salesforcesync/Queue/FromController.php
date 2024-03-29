<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_Queue_FromController extends Mage_Adminhtml_Controller_Action
{

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('tnw_salesforce/queue_sync/from_salesforce');
    }

    /**
     * @return $this
     */
    protected function _initLayout()
    {
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce');

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Queue Objects Synchronization'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_queue_from'));
        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');

        $this->renderLayout();
    }

    /**
     *
     */
    public function massDeleteAction()
    {
        $ids = $this->getRequest()->getParam('import_ids');

        if (!is_array($ids)) {
            $this->_getSession()->addError($this->__('Please select Item(s).'));
        } else {
            try {
                foreach ($ids as $id) {
                    $model = Mage::getSingleton('tnw_salesforce/import')->load($id);
                    $model->delete();
                }

                $this->_getSession()->addSuccess(
                    $this->__('Total of %d record(s) have been deleted.', count($ids))
                );
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception $e) {
                $this->_getSession()->addError(
                    Mage::helper('')->__('An error occurred while mass deleting items. Please review log and try again.')
                );
                Mage::logException($e);
                return;
            }
        }
        $this->_redirect('*/*/');
    }


    /**
     * Grid Action
     */
    public function gridAction()
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     *
     */
    public function syncAction()
    {
        $queueId = $this->getRequest()->getParam('queue');
        /** @var TNW_Salesforce_Model_Import $queue */
        $queue = Mage::getModel('tnw_salesforce/import')
            ->load($queueId);

        if (is_null($queue->getId())) {
            $this->_getSession()
                ->addError("Item id \"{$queueId}\" not found");

            $this->_redirect('*/*/');
            return;
        }

        $queue
            ->setStatus(TNW_Salesforce_Model_Import::STATUS_PROCESSING)
            ->save(); //Update status to prevent duplication

        $association = array();
        try {
            $_association = $queue->process();
            foreach($_association as $type=>$_item) {
                if (!isset($association[$type])) {
                    $association[$type] = array();
                }

                $association[$type] = array_merge($association[$type], $_item);
            }

            $queue
                ->setStatus(TNW_Salesforce_Model_Import::STATUS_SUCCESS)
                ->save();
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveError("Error: {$e->getMessage()}");

            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace("Failed to upsert a {$queue->getObjectType()} #{$queue->getObjectProperty('Id')}, please re-save or re-import it manually");

            $queue
                ->setMessage($e->getMessage())
                ->setStatus(TNW_Salesforce_Model_Import::STATUS_ERROR)
                ->save();
        }

        $queue->sendMagentoIdToSalesforce($association);

        $this->_redirect('*/*/');
    }
}