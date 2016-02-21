<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Product_Observer
{
    public function __construct()
    {
    }

    /**
     * @param $observer
     */
    public function duplicateBefore($observer)
    {
        /**
         * @var Mage_Catalog_Model_Product $_newProduct
         */
        $_newProduct = $observer->getEvent()->getNewProduct();

        $_newProduct->unsSalesforceId();
        $_newProduct->unsSalesforcePricebookId();

        return;
    }

    /**
     * @param $observer
     */
    public function salesforceTriggerEvent($observer)
    {
       $_product = $observer->getEvent()->getProduct();

        Mage::helper("tnw_salesforce")->log('MAGENTO EVENT: Product #' . $_product->getId() . ' Sync');

        Mage::dispatchEvent('tnw_catalog_product_save', array('product' => $_product));

        return;
    }

    /**
     * @param $observer
     * @return bool
     */
    public function salesforcePush($observer)
    {
        if (Mage::getSingleton('core/session')->getFromSalesForce()) {
            Mage::helper("tnw_salesforce")->log('INFO: Updating from Salesforce, skip synchronization to Salesforce.');
            return; // Disabled
        }
        $_product = $observer->getEvent()->getProduct();
        Mage::helper("tnw_salesforce")->log('TNW EVENT: Product #' . $_product->getId() . ' Sync');

        if (
            !Mage::helper('tnw_salesforce')->isEnabled()
            || !Mage::helper('tnw_salesforce')->isEnabledProductSync()
        ) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Product synchronization disabled');
            return; // Disabled sync
        } else if ($_product->getIsDuplicate()) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Product duplicate process');
            return; //
        } else if (!Mage::helper('tnw_salesforce')->canPush()) {
            Mage::helper("tnw_salesforce")->log('ERROR: Salesforce connection could not be established, SKIPPING product sync');
            return; // Disabled
        } else if (
            $_product->getSuperProduct() &&
            $_product->getSuperProduct()->isConfigurable()
        ) {
            Mage::helper("tnw_salesforce")->log('SKIPING: Configurable Product');
            return; // Only simple
        } else {
            // check if queue sync setting is on - then save to database
            if (Mage::helper('tnw_salesforce')->getObjectSyncType() != 'sync_type_realtime') {
                // pass data to local storage
                // TODO add level up abstract class with Order as static values, now we have word 'Product' as parameter
                $res = Mage::getModel('tnw_salesforce/localstorage')->addObjectProduct(array(intval($_product->getData('entity_id'))), 'Product', 'product');
                if (!$res) {
                    Mage::helper("tnw_salesforce")->log('error: product not saved to local storage');
                    return false;
                }
                return true;
            }

            if (!Mage::getSingleton('core/session')->getFromSalesForce()) {
                $manualSync = Mage::helper('tnw_salesforce/salesforce_product');
                $manualSync->reset();
                $manualSync->updateMagentoEntityValue($_product->getId(), NULL, 'sf_insync', 'catalog_product_entity_int', 0);
                foreach (Mage::app()->getStores() as $_storeId => $_store) {
                    $manualSync->updateMagentoEntityValue($_product->getId(), NULL, 'sf_insync', 'catalog_product_entity_int', $_storeId);
                }
                $manualSync->processSql();
            } else {
                // Skip, coming from Salesforce
                return;
            }

            $manualSync = Mage::helper('tnw_salesforce/salesforce_product');
            $manualSync->setSalesforceServerDomain(Mage::getSingleton('core/session')->getSalesforceServerDomain());
            $manualSync->setSalesforceSessionId(Mage::helper('tnw_salesforce/test_authentication')->getStorage('salesforce_session_id'));

            if ($manualSync->reset()) {
                $manualSync->massAdd(array($_product->getId()));
                $manualSync->process();
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('Product (sku: ' . $_product->getSku() . ') is successfully synchronized'));
            } else {
                Mage::getSingleton('adminhtml/session')->addError('Salesforce Connection failed!');
            }
        }
    }

    public function beforeImport()
    {
        Mage::getSingleton('core/session')->setFromSalesForce(true);
    }

    public function afterImport()
    {
        Mage::getSingleton('core/session')->setFromSalesForce(false);
    }
}