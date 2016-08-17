<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_Order extends TNW_Salesforce_Helper_Salesforce_Data
{
    const DISABLED_STATUS_CODE = 'D';
    const ACTIVATED_STATUS_CODE = 'A';
    const ACTIVATED_STATUS = 'Activated';
    const DRAFT_STATUS = 'Draft';

    /**
     * @param array $ids
     * @return array|bool
     */
    public function lookup($ids = array())
    {
        $ids = !is_array($ids)
            ? array($ids) : $ids;

        try {
            if (!is_object($this->getClient())) {

                return false;
            }
            $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . "Magento_ID__c";

            $_results = array();
            foreach (array_chunk($ids, self::UPDATE_LIMIT) as $_ids) {
                $result = $this->_queryOrder($_magentoId, $_ids);
                if (empty($result) || $result->size < 1) {
                    continue;
                }

                $_results[] = $result;
            }

            if (empty($_results)) {
                Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order lookup returned: no results...");
                return false;
            }

            $returnArray = array();
            foreach ($_results as $result) {
                foreach ($result->records as $_item) {
                    $tmp = new stdClass();
                    $tmp->Id = $_item->Id;
                    $tmp->AccountId = (property_exists($_item, "AccountId")) ? $_item->AccountId : NULL;
                    $tmp->Pricebook2Id = (property_exists($_item, "Pricebook2Id")) ? $_item->Pricebook2Id : NULL;
                    $tmp->Status = (property_exists($_item, "Status")) ? $_item->Status : NULL;
                    $tmp->StatusCode = (property_exists($_item, "StatusCode")) ? $_item->StatusCode : self::DISABLED_STATUS_CODE;
                    $tmp->MagentoId = $_item->$_magentoId;
                    $tmp->OrderItems = (property_exists($_item, "OrderItems")) ? $_item->OrderItems : NULL;
                    $tmp->Notes = (property_exists($_item, "Notes")) ? $_item->Notes : NULL;
                    $tmp->hasReductionOrder = (property_exists($_item, "Orders")) ? $_item->Orders->size > 0 : false;
                    $tmp->Orders = (property_exists($_item, "Orders")) ? $_item->Orders : false;

                    $returnArray[$tmp->MagentoId] = $tmp;
                }
            }

            return $returnArray;
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Could not find any existing orders in Salesforce matching these IDs (" . implode(",", $ids) . ")");
            unset($email);
            return false;
        }
    }

    /**
     * @param $_magentoId
     * @param $ids
     * @return array|stdClass
     */
    protected function _queryOrder($_magentoId, $ids)
    {
        $orderItemFieldsToSelect = 'Id, Quantity, UnitPrice, PricebookEntry.ProductCode, PricebookEntry.Product2Id, PricebookEntryId, Description, PricebookEntry.UnitPrice, PricebookEntry.Name';

        if (!Mage::helper('tnw_salesforce/data')->isProfessionalSalesforceVersionType()) {
            $orderItemFieldsToSelect .=   ' , AvailableQuantity';
        }

        $_selectFields = array(
            "ID",
            "AccountId",
            "Pricebook2Id",
            "StatusCode",
            "Status",
            $_magentoId,
            "(SELECT $orderItemFieldsToSelect FROM OrderItems)",
            "(SELECT Id, Title, Body FROM Notes)"
        );

        if (!Mage::helper('tnw_salesforce/data')->isProfessionalSalesforceVersionType()) {
            $_selectFields[] = "(SELECT Id FROM Orders)";
        }

        $query = "SELECT " . implode(',', $_selectFields) . " FROM Order WHERE " . $_magentoId . " IN ('" . implode("','", $ids) . "')";

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("QUERY: " . $query);
        try {
            $result = $this->getClient()->query($query);
        } catch (Exception $e) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR: " . $e->getMessage());
            $result = array();
        }

        return $result;
    }
}