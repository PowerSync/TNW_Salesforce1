<?php

class TNW_Salesforce_Adminhtml_Salesforcesync_WishlistsyncController extends Mage_Adminhtml_Controller_Action
{
    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/tnw_salesforce/manual_sync/wishlist')
            && Mage::helper('tnw_salesforce')->isProfessionalEdition();
    }

    /**
     * @return $this
     */
    protected function _initLayout()
    {
        $this->loadLayout()
            ->_setActiveMenu('tnw_salesforce')
            ->_addBreadcrumb($this->__('Manual Wishlist Synchronization'), $this->__('Manual Wishlist Synchronization'));

        return $this;
    }

    /**
     * Index Action
     */
    public function indexAction()
    {
        $this
            ->_title($this->__('System'))
            ->_title($this->__('Salesforce API'))
            ->_title($this->__('Manual Sync'))
            ->_title($this->__('Wishlist'));

        $this->_initLayout()
            ->_addContent($this->getLayout()->createBlock('tnw_salesforce/adminhtml_synchronize_wishlist'));

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
     * @throws \Exception
     */
    public function syncAction()
    {
        $entityId = $this->getRequest()->getParam('wishlist_id');
        Mage::getSingleton('tnw_salesforce/wishlist_observer')
            ->syncWishlist(array($entityId));

        $this->_redirectReferer();
    }

    /**
     * @throws \Exception
     */
    public function massSyncAction()
    {
        $itemIds = $this->getRequest()->getParam('wishlist_ids');
        if (!is_array($itemIds)) {
            $this->_getSession()->addError($this->__('Please select wishlist(s)'));
        } elseif (!Mage::helper('tnw_salesforce')->isProfessionalEdition()) {
            $this->_getSession()->addError($this->__('Mass syncronization is not allowed using Basic version. Please visit <a href="http://powersync.biz" target="_blank">http://powersync.biz</a> to request an upgrade.'));
        } else {
            Mage::getSingleton('tnw_salesforce/wishlist_observer')
                ->syncWishlist($itemIds);
        }

        $this->_redirect('*/*/index');
    }
}
