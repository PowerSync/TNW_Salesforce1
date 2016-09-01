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
        $collection = Mage::getResourceModel('tnw_salesforce/import_collection')
            ->getOnlyPending()
            ->setPageSize(self::PAGE_SIZE);

        $association = array();
        /** @var TNW_Salesforce_Model_Import $item */
        foreach ($collection as $item) {
            $item
                ->setIsProcessing(1)
                ->save(); //Update status to prevent duplication

            try {
                $_association = $item->process();
                foreach($_association as $type=>$_item) {
                    if (!isset($association[$type])) {
                        $association[$type] = array();
                    }

                    $association[$type] = array_merge($association[$type], $_item);
                }

                $item->delete();
            }
            catch (Exception $e) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveError("Error: " . $e->getMessage());
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Failed to upsert a " . $item->getObjectType()
                    . " #" . $item->getObjectProperty('Id') . ", please re-save or re-import it manually");
            }

            set_time_limit(30); //Reset Script execution time limit
        }

        if (!empty($association)) {
            TNW_Salesforce_Helper_Magento_Abstract::sendMagentoIdToSalesforce($association);
        }
        return true;
    }
}