<?php

/**
 * Class TNW_Salesforce_Adminhtml_Salesforcesync_CustomersyncController
 */
class TNW_Salesforce_Adminhtml_Salesforcesync_CustomersyncController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = array('grid', 'index');

    protected function _initLayout()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled() || !Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
            Mage::getSingleton('adminhtml/session')->addNotice("Salesforce integration is not working! Refer to the config or the log files for more information.");
        }
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Customer Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Customer Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Customer'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_customersync'));
        Mage::helper('tnw_salesforce')->addAdminhtmlVersion('TNW_Salesforce');
        $this->renderLayout();
    }

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
        if ($this->getRequest()->getParam('customer_id') > 0) {
            try {
                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    // pass data to local storage
                    // TODO add level up abstract class with Order as static values, now we have word 'Customer' as parameter
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObject(array($this->getRequest()->getParam('customer_id')), 'Customer', 'customer');
                    if (!$res) {
                        Mage::getSingleton('adminhtml/session')->addError('Could not add customer to the queue!');
                    } else {
                        if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess(
                                Mage::helper('adminhtml')->__('Record was added to synchronization queue!')
                            );
                        }
                    }
                } else {
                    $manualSync = Mage::helper('tnw_salesforce/salesforce_customer');
                    if ($manualSync->reset()) {
                        $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
                        $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

                        $manualSync->massAdd(array($this->getRequest()->getParam('customer_id')));
                        $manualSync->process();
                        if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess(
                                Mage::helper('adminhtml')->__('Record was syncronized successfully!')
                            );
                        }
                    } else {
                        Mage::getSingleton('adminhtml/session')->addError('Salesforce Connection failed!');
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/');
            }
        }
        $this->_redirect('*/*/');
    }

    public function massSyncAction()
    {
        if (!Mage::helper('tnw_salesforce')->isEnabled()) {
            Mage::getSingleton('adminhtml/session')->addError("API Integration is disabled.");
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl("adminhtml/system_config/edit", array('section' => 'salesforce')));
            Mage::app()->getResponse()->sendResponse();
        }
        $itemIds = $this->getRequest()->getParam('customers');
        if (!is_array($itemIds)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Please select customer(s)'));
        } elseif (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } elseif(((Mage::helper('tnw_salesforce')->getObjectSyncType() == 'sync_type_realtime')) && (count($itemIds) > 50)) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('tnw_salesforce')->__('For history synchronization containing more than 50 records change configuration to use interval based synchronization.'));
        } else {
            try {
                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    $_chunks = array_chunk($itemIds, TNW_Salesforce_Helper_Queue::UPDATE_LIMIT);
                    unset($itemIds);
                    foreach($_chunks as $_chunk) {
                        Mage::helper('tnw_salesforce/queue')->prepareRecordsToBeAddedToQueue($_chunk, 'Customer', 'customer');
                    }

                    if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                        Mage::getSingleton('adminhtml/session')->addSuccess(
                            $this->__('Records are pending addition into the queue!')
                        );
                    }
                } else {
                    $manualSync = Mage::helper('tnw_salesforce/bulk_customer');
                    if ($manualSync->reset()) {
                        $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
                        $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

                        $manualSync->massAdd($itemIds);
                        $manualSync->process();
                        if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()
                            && Mage::helper('tnw_salesforce/salesforce_data')->isLoggedIn()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess(
                                Mage::helper('adminhtml')->__('Total of %d record(s) were successfully synchronized', count($itemIds))
                            );
                        }
                    } else {
                        Mage::getSingleton('adminhtml/session')->addError('Salesforce connection could not be established!');
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }
        $this->_redirect('*/*/index');
    }

    /**
     * Check current user permission
     *
     * @return boolean
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('tnw_salesforce/customersync');
    }
}
