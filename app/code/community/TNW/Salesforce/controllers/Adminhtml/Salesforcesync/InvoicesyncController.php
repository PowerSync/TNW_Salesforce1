<?php

class TNW_Salesforce_Adminhtml_Salesforcesync_InvoicesyncController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = array('grid', 'index');

    protected function _construct()
    {
        $this->setUsedModuleName('Mage_Sales');
    }

    protected function _initLayout()
    {
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Invoice Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Invoice Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Invoices'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_invoicesync'));
        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');

        $this->renderLayout();
    }

    /**
     * Order grid
     */
    public function gridAction()
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Sync Action
     *
     */
    public function syncAction()
    {
        $entityId = $this->getRequest()->getParam('invoice_id');

        $this->syncEntity(array($entityId));
        $this->_redirectReferer();
    }

    public function massSyncForceAction()
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $itemIds = $this->getRequest()->getParam('invoice_ids');
        if (!is_array($itemIds)) {
            $this->_getSession()->addError($helper->__('Please select orders(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $this->_getSession()->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            $this->syncEntity($itemIds);
        }

        $this->_redirect('*/*/index');
    }

    /**
     * @param array $entityIds
     */
    protected function syncEntity(array $entityIds)
    {
        /** check empty */
        if (empty($entityIds)) {
            return;
        }

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        /** @var Varien_Db_Select $select */
        $select = TNW_Salesforce_Model_Localstorage::generateSelectForType('sales/order_invoice', $entityIds);

        $groupWebsite = array();
        foreach ($select->getAdapter()->fetchAll($select) as $row) {
            $groupWebsite[$row['website_id']][] = $row['object_id'];
        }

        /** @var Mage_Core_Model_App_Emulation $appEmulation */
        $appEmulation = Mage::getSingleton('core/app_emulation');
        foreach ($groupWebsite as $websiteId => $entityIds) {
            $website = Mage::app()->getWebsite($websiteId);
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($website->getDefaultStore()->getId());

            if (!$helper->isEnabled()) {
                $this->_getSession()->addError(sprintf('API Integration is disabled in Website: %s', $website->getName()));
            }
            else {
                $syncBulk = (count($entityIds) > 1);

                try {
                    if (count($entityIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                        $success = Mage::getModel('tnw_salesforce/localstorage')
                            ->addObject($entityIds, 'Invoice', 'invoice', $syncBulk);

                        if ($success) {
                            if ($syncBulk) {
                                $this->_getSession()->addNotice($this->__('ISSUE: Too many records selected.'));
                                $this->_getSession()->addSuccess($this->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', $this->getUrl('*/salesforcesync_queue_to/bulk')));
                            }
                            else {
                                $this->_getSession()->addSuccess($this->__('Records are pending addition into the queue!'));
                            }
                        }
                        else {
                            $this->_getSession()->addError('Could not add to the queue!');
                        }
                    }
                    else {
                        $_syncType = strtolower(Mage::helper('tnw_salesforce')->getInvoiceObject());
                        Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                            'invoiceIds' => $entityIds,
                            'message' => $this->__('Total of %d records(s) were synchronized in Website: %s', count($entityIds), $website->getName()),
                            'type' => $syncBulk ? 'bulk' : 'salesforce'
                        ));
                    }
                } catch (Exception $e) {
                    $this->_getSession()->addError($e->getMessage());
                }
            }

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }
    }
}
