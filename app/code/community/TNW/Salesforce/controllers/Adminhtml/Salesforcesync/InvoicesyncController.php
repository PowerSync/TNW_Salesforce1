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
        if (!Mage::helper('tnw_salesforce')->isEnabled() || !Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::getSingleton('adminhtml/session')->addNotice("Salesforce integration is not working! Refer to the config or the log files for more information.");
        }
        $this->loadLayout()
            ->_setActiveMenu('system/salesforce')
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
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('adminhtml/session')->addError("API Integration is disabled.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));
            Mage::app()->getResponse()->sendResponse();
        }
        if ($this->getRequest()->getParam('invoice_id') > 0) {
            try {
                $itemIds = array($this->getRequest()->getParam('invoice_id'));

                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    // pass data to local storage
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObject($itemIds, 'Invoice', 'invoice');
                    if (!$res) {
                        Mage::getSingleton('adminhtml/session')->addError('Could not add invoice to the queue!');
                    } else {
                        if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess(
                                Mage::helper('adminhtml')->__('Invoice was added to the queue!')
                            );
                        }
                    }
                } else {
                    Mage::dispatchEvent('tnw_salesforce_invoice_process', array(
                        'invoiceIds' => $itemIds,
                        'message'    => $this->__('Total of %d record(s) were successfully synchronized', count($itemIds)),
                        'type'       => 'salesforce'
                    ));
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/');
            }
        }
        $this->_redirect('*/*/');
    }

    public function massSyncForceAction()
    {
        set_time_limit(0);
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('adminhtml/session')->addError("API Integration is disabled.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));
            Mage::app()->getResponse()->sendResponse();
        }
        $itemIds = $this->getRequest()->getParam('invoice_ids');
        if (!is_array($itemIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Please select orders(s)'));
        } elseif (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } elseif(((Mage::helper('tnw_salesforce')->getObjectSyncType() == 'sync_type_realtime')) && (count($itemIds) > 50)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('For history synchronization containing more than 50 records change configuration to use interval based synchronization.'));
        } else {
            try {
                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    $_chunks = array_chunk($itemIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT);
                    unset($itemIds, $_chunk);
                    foreach($_chunks as $_chunk) {
                        Mage::helper('tnw_salesforce/queue')->prepareRecordsToBeAddedToQueue($_chunk, 'Invoice', 'invoice');
                    }

                    if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                        Mage::getSingleton('adminhtml/session')->addSuccess(
                            $this->__('Records are pending addition into the queue!')
                        );
                    }
                } else {
                    Mage::dispatchEvent('tnw_salesforce_invoice_process', array(
                        'invoiceIds' => $itemIds,
                        'message'    => Mage::helper('adminhtml')->__('Total of %d records(s) were synchronized', count($itemIds)),
                        'type'       => 'bulk'
                    ));
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }
}
