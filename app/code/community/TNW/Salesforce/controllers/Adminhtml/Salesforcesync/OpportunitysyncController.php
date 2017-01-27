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
        Mage::getSingleton('tnw_salesforce/sale_observer')->syncOrder(array($entityId));

        $this->_redirect('*/*/');
    }

    public function massSyncForceAction()
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper  = Mage::helper('tnw_salesforce');

        $itemIds = $this->getRequest()->getParam('orders');
        if (!is_array($itemIds)) {
            $this->_getSession()->addError($helper->__('Please select orders(s)'));
        } elseif (!$helper->isProfessionalEdition()) {
            $this->_getSession()->addError($helper->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            Mage::getSingleton('tnw_salesforce/sale_observer')->syncOrder($itemIds);
        }
        $this->_redirect('*/*/index');
    }

    public function syncWebsitesAction()
    {
        $websiteIds = array_map(function(Mage_Core_Model_Website $website) {
            return $website->getId();
        }, Mage::app()->getWebsites(true));

        Mage::getSingleton('tnw_salesforce/website_observer')
            ->syncWebsite($websiteIds);

        $this->_redirect('*/system_store/index');
    }

    public function syncCurrencyAction()
    {
        foreach (Mage::helper('tnw_salesforce/config')->getWebsitesDifferentConfig() as $website) {
            Mage::helper('tnw_salesforce/config')->wrapEmulationWebsite($website, function() {
                /** @var TNW_Salesforce_Helper_Data $_helperData */
                $_helperData = Mage::helper('tnw_salesforce');
                if (!$_helperData->isEnabled() || !$_helperData->isMultiCurrency()) {
                    return;
                }

                $currencies = Mage::getModel('directory/currency')
                    ->getConfigAllowCurrencies();

                try {
                    $manualSync = Mage::helper('tnw_salesforce/salesforce_currency');
                    if ($manualSync->reset() && $manualSync->massAdd($currencies) && $manualSync->process()) {
                        Mage::getSingleton('tnw_salesforce/tool_log')
                            ->saveSuccess($_helperData->__('%d Magento currency entities were successfully synchronized', count($currencies)));
                    }
                } catch (Exception $e) {
                    Mage::getSingleton('tnw_salesforce/tool_log')
                        ->saveError($e->getMessage());
                }
            });
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
