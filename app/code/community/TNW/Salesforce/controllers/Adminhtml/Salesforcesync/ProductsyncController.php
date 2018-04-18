<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Adminhtml_Salesforcesync_ProductsyncController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Array of actions which can be processed without secret key validation
     *
     * @var array
     */
    protected $_publicActions = array('grid', 'index');

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('tnw_salesforce/manual_sync/product_sync');
    }

    /**
     * @return $this
     */
    protected function _initLayout()
    {
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb(Mage::helper('tnw_salesforce')->__('Manual Product Synchronization'), Mage::helper('tnw_salesforce')->__('Manual Product Synchronization'));

        return $this;
    }

    /**
     * Index Action
     *
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('Salesforce API'))->_title($this->__('Manual Sync'))->_title($this->__('Products'));

        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('adminhtml/store_switcher'));
        $this->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_synchronize_product'));

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
     * @throws Exception
     */
    public function syncAction()
    {
        $productId = $this->getRequest()->getParam('product_id');
        Mage::dispatchEvent('tnw_salesforce_product_save', array(
            'product_ids' => array($productId),
            'is_manual_sync' => true
        ));

        $this->_redirectReferer($this->getUrl('*/*/index', array('_current' => true)));
    }

    public function massSyncAction()
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper  = Mage::helper('tnw_salesforce');

        $itemIds = $this->getRequest()->getParam('products');
        if (!is_array($itemIds)) {
            $this->_getSession()->addError($helper->__('Please select products(s)'));
        } else {
            Mage::dispatchEvent('tnw_salesforce_product_save', array(
                'product_ids' => $itemIds,
                'is_manual_sync' => true
            ));
        }
        $url = '*/*/index';
        if ($helper->getStoreId() != 0) {
            $url .= '/store/' . Mage::helper('tnw_salesforce')->getStoreId();
        }
        $this->_redirect($url);
    }
}