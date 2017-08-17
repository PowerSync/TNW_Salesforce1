<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Imports_Bulk
{
    const PAGE_SIZE = 50;

    /**
     * @return bool
     */
    public function process()
    {
        Mage::getResourceModel('tnw_salesforce/import_collection')
            ->filterEnding()->removeAll();

        // Process ordered
        $orderedType = array(
            Mage::helper('tnw_salesforce/config')->getMagentoWebsiteField(),
            'Product2',
            'Account',
            'Contact',
            'Order',
            'Opportunity',
            TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT,
            TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT,
        );

        foreach ($orderedType as $type) {
            $this->processType($type);
        }

        // Process other
        $this->processType(null);
        return true;
    }

    /**
     * @param null|string $objectType
     * @throws Exception
     */
    protected function processType($objectType)
    {
        $collection = Mage::getResourceModel('tnw_salesforce/import_collection')
            ->filterPending();

        if (null !== $objectType) {
            $collection->filterObjectType($objectType);
        }
        $collection->setPageSize(Mage::helper('tnw_salesforce/config_bulk')->getPageSizeFromSalesforce());
        $lastPageNumber = $collection->getLastPageNumber();
        if (Mage::helper('tnw_salesforce/config_bulk')->getPageCountFromSalesforce() > 0) {
            $lastPageNumber = min($lastPageNumber, Mage::helper('tnw_salesforce/config_bulk')->getPageCountFromSalesforce());
        }

        for($i = 1; $i <= $lastPageNumber; $i++) {
            $collection->clear()->setCurPage($i);

            $association = array();
            /** @var TNW_Salesforce_Model_Import $item */
            foreach ($collection as $item) {
                $item
                    ->setStatus(TNW_Salesforce_Model_Import::STATUS_PROCESSING)
                    ->save(); //Update status to prevent duplication

                try {
                    $_association = $item->process();
                    foreach($_association as $type=>$_item) {
                        if (!isset($association[$type])) {
                            $association[$type] = array();
                        }

                        $association[$type] = array_merge($association[$type], $_item);
                    }

                    $item
                        ->setStatus(TNW_Salesforce_Model_Import::STATUS_SUCCESS)
                        ->save();
                }
                catch (Exception $e) {
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error: " . $e->getMessage());
                    Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Failed to upsert a " . $item->getObjectType()
                        . " #" . $item->getObjectProperty('Id') . ", please re-save or re-import it manually");

                    $item
                        ->setMessage($e->getMessage())
                        ->setStatus(TNW_Salesforce_Model_Import::STATUS_ERROR)
                        ->save();
                }

                set_time_limit(30); //Reset Script execution time limit
            }

            if (!empty($association)) {
                switch ($objectType) {
                    case Mage::helper('tnw_salesforce/config')->getMagentoWebsiteField():
                        TNW_Salesforce_Helper_Magento_Websites::sendMagentoIdToSalesforce($association);
                        break;

                    case TNW_Salesforce_Model_Config_Objects::ORDER_INVOICE_OBJECT:
                        TNW_Salesforce_Helper_Magento_Invoice::sendMagentoIdToSalesforce($association);
                        break;

                    case TNW_Salesforce_Model_Config_Objects::ORDER_SHIPMENT_OBJECT:
                        TNW_Salesforce_Helper_Magento_Shipment::sendMagentoIdToSalesforce($association);
                        break;

                    default:
                        TNW_Salesforce_Helper_Magento_Abstract::sendMagentoIdToSalesforce($association);
                        break;
                }
            }
        }
    }
}