<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_OpportunitysyncController extends Mage_Adminhtml_Controller_Action
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
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Order Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Order Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Orders'));
        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_ordersync'));
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
        $entityId = $this->getRequest()->getParam('order_id');

        $this->syncEntity(array($entityId));
        $this->_redirect('*/*/');
    }

    public function massSyncForceAction()
    {
        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper  = Mage::helper('tnw_salesforce');

        $itemIds = $this->getRequest()->getParam('orders');
        if (!is_array($itemIds)) {
            $session->addError($helper->__('Please select orders(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $session->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
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

        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        /** @var Varien_Db_Select $select */
        $select = TNW_Salesforce_Model_Localstorage::generateSelectForType('sales/order', $entityIds);

        $groupWebsite = array();
        foreach ($select->getAdapter()->fetchAll($select) as $row) {
            $groupWebsite[$row['website_id']][] = $row['object_id'];
        }

        /** @var Mage_Core_Model_App_Emulation $appEmulation */
        $appEmulation = Mage::getSingleton('core/app_emulation');
        foreach ($groupWebsite as $websiteId => $entityIds) {
            $storeId = Mage::app()->getWebsite($websiteId)->getDefaultStore()->getId();
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);

            if (!$helper->isEnabled()) {
                $session->addError(sprintf('API Integration is disabled in Website: %s', Mage::app()->getWebsite($websiteId)->getName()));
            }
            else {
                $syncBulk = (count($entityIds) > 1);

                try {
                    if (count($entityIds) > $helper->getRealTimeSyncMaxCount() || !$helper->isRealTimeType()) {
                        $_collection = Mage::getResourceModel('sales/order_item_collection')
                            ->addFieldToFilter('order_id', array('in' => $entityIds));

                        // use Mage::helper('tnw_salesforce/salesforce_order')->getProductIdFromCart(
                        $productIds = $_collection->walk(array(
                            Mage::helper('tnw_salesforce/salesforce_opportunity'), 'getProductIdFromCart'
                        ));

                        $success = Mage::getModel('tnw_salesforce/localstorage')
                            ->addObjectProduct(array_unique($productIds), 'Product', 'product', $syncBulk);

                        $success = $success && Mage::getModel('tnw_salesforce/localstorage')
                                ->addObject($entityIds, 'Order', 'order', $syncBulk);

                        if ($success) {
                            if ($syncBulk) {
                                $session->addNotice($this->__('ISSUE: Too many records selected.'));
                                $session->addSuccess($this->__('Selected records were added into <a href="%s">synchronization queue</a> and will be processed in the background.', $this->getUrl('*/salesforcesync_queue_to/bulk')));
                            } else {
                                $session->addSuccess($this->__('Records are pending addition into the queue!'));
                            }
                        }
                        else {
                            $session->addError('Could not add to the queue!');
                        }
                    }
                    else {
                        Mage::dispatchEvent('tnw_salesforce_opportunity_process', array(
                            'orderIds'  => $entityIds,
                            'message'   => $this->__('Total of %d record(s) were successfully synchronized', count($entityIds)),
                            'type'      => $syncBulk ? 'bulk' : 'salesforce'
                        ));
                    }
                } catch (Exception $e) {
                    $session->addError($e->getMessage());
                }
            }

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }
    }

    public function syncWebsitesAction()
    {
        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        /** @var TNW_Salesforce_Helper_Config $helperConfig */
        $helperConfig = Mage::helper('tnw_salesforce/config');

        /** @var Mage_Core_Model_App_Emulation $appEmulation */
        $appEmulation = Mage::getSingleton('core/app_emulation');

        /** @var Mage_Core_Model_Website $website */
        foreach ($helperConfig->getWebsiteDifferentConfig() as $website) {
            $storeId = $website->getDefaultStore()->getId();
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);

            if (!$helper->isEnabled()) {
                $session->addError(sprintf('API Integration is disabled in Website: %s', $website->getName()));
            }
            else {
                try {
                    $websites = ($website->getId() == Mage::app()->getWebsite('admin')->getId())
                        ? array_keys(array_diff_key(Mage::app()->getWebsites(true), $helperConfig->getWebsiteDifferentConfig(false)))
                        : array($website->getId());

                    /** @var TNW_Salesforce_Helper_Salesforce_Website $manualSync */
                    $manualSync = Mage::helper('tnw_salesforce/salesforce_website');
                    if ($manualSync->reset() && $manualSync->massAdd($websites) && $manualSync->process()) {
                        $session->addSuccess($this->__('Magento website entities were successfully synchronized in Website: %s', $website->getName()));
                    }
                } catch (Exception $e) {
                    $session->addError($e->getMessage());
                }
            }

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }

        $this->_redirect('*/system_store/index');
    }

    public function syncCurrencyAction()
    {
        /** @var Mage_Adminhtml_Model_Session $session */
        $session = Mage::getSingleton('adminhtml/session');

        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        /** @var TNW_Salesforce_Helper_Config $helperConfig */
        $helperConfig = Mage::helper('tnw_salesforce/config');

        /** @var Mage_Core_Model_App_Emulation $appEmulation */
        $appEmulation = Mage::getSingleton('core/app_emulation');

        foreach ($helperConfig->getWebsiteDifferentConfig() as $website) {
            $storeId = $website->getDefaultStore()->getId();
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);

            if ($helper->isMultiCurrency()) {
                try {
                    /** @var Mage_Directory_Model_Currency $currencyModel */
                    $currencyModel = Mage::getModel('directory/currency');
                    $currencies = $currencyModel->getConfigAllowCurrencies();

                    /** @var TNW_Salesforce_Helper_Salesforce_Currency $manualSync */
                    $manualSync = Mage::helper('tnw_salesforce/salesforce_currency');
                    if ($manualSync->reset() && $manualSync->massAdd($currencies) && $manualSync->process()) {
                        $session->addSuccess($this->__('Magento currency entities were successfully synchronized in Website: %s', $website->getName()));
                    }
                } catch (Exception $e) {
                    $session->addError($e->getMessage());
                }
            }

            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }

        $this->_redirect('*/system_currency/index');
    }

    public function massCartSyncAction()
    {
        $this->_redirect('*/*/index');
    }

    public function massNotesSyncAction()
    {
        $this->_redirect('*/*/index');
    }
}
