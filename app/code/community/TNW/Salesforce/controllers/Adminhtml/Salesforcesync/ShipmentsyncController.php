<?php

class TNW_Salesforce_Adminhtml_Salesforcesync_ShipmentsyncController extends Mage_Adminhtml_Controller_Action
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
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Shipment Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Shipment Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Shipments'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_shipmentsync'));
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
        if ($this->getRequest()->getParam('shipment_id') > 0) {
            try {
                $itemIds = array($this->getRequest()->getParam('shipment_id'));

                if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                    // pass data to local storage
                    $res = Mage::getModel('tnw_salesforce/localstorage')->addObject($itemIds, 'Shipment', 'shipment');
                    if (!$res) {
                        Mage::getSingleton('adminhtml/session')->addError('Could not add shipment to the queue!');
                    } else {
                        if (!Mage::getSingleton('adminhtml/session')->getMessages()->getErrors()) {
                            Mage::getSingleton('adminhtml/session')->addSuccess(
                                Mage::helper('adminhtml')->__('Shipment was added to the queue!')
                            );
                        }
                    }
                } else {
                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getShipmentObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'shipmentIds' => $itemIds,
                        'message'     => $this->__('Total of %d record(s) were successfully synchronized', count($itemIds)),
                        'type'        => 'salesforce'
                    ));
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            }
        }

        $this->_redirectReferer();
    }

    public function massSyncForceAction()
    {
        $session = Mage::getSingleton('adminhtml/session');
        $helper  = Mage::helper('tnw_salesforce');

        if (!$helper->isEnabled()) {
            $session->addError("API Integration is disabled.");
            $this->_redirect("adminhtml/system_config/edit", array('section' => 'salesforce'));
            return;
        }

        $itemIds = $this->getRequest()->getParam('shipment_ids');
        if (!is_array($itemIds)) {
            $session->addError($helper->__('Please select shipment(s)'));
        } elseif (!Mage::helper('tnw_salesforce')->isProfessionalEdition()) {
            $session->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            try {
                if (count($itemIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                    $syncBulk = (count($itemIds) > 1);

                    $success = Mage::getModel('tnw_salesforce/localstorage')
                        ->addObjectProduct($itemIds, 'Shipment', 'shipment', $syncBulk);

                    if ($success) {
                        if ($syncBulk) {
                            $session->addNotice($this->__('ISSUE: Too many records selected.'));
                            $session->addSuccess($this->__('Selected records were added into synchronization queue and will be processed in the background.'));
                        }
                        else {
                            $session->addSuccess($this->__('Records are pending addition into the queue!'));
                        }
                    }
                    else {
                        $session->addError('Could not add to the queue!');
                    }
                }
                else {
                    $_syncType = strtolower(Mage::helper('tnw_salesforce')->getShipmentObject());
                    Mage::dispatchEvent(sprintf('tnw_salesforce_%s_process', $_syncType), array(
                        'shipmentIds' => $itemIds,
                        'message'    => $this->__('Total of %d records(s) were synchronized', count($itemIds)),
                        'type'       => 'bulk'
                    ));
                }
            } catch (Exception $e) {
                $session->addError($e->getMessage());
            }
        }

        $this->_redirect('*/*/index');
    }
}
