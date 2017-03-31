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

        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

        $_results = array();
        foreach (array_chunk($ids, self::UPDATE_LIMIT) as $_ids) {
            $_results[] = $this->_queryOrder($_magentoId, $_ids);
        }

        $records = $this->mergeRecords($_results);
        if (empty($records)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Order lookup returned: no results...');

            return array();
        }

        $returnArray = array();
        foreach ($records as $_item) {
            $tmp = new stdClass();
            $tmp->Id = $_item->Id;
            $tmp->AccountId = $this->getProperty($_item, 'AccountId');
            $tmp->Pricebook2Id = $this->getProperty($_item, 'Pricebook2Id');
            $tmp->Status = $this->getProperty($_item, 'Status');
            $tmp->StatusCode =  $this->getProperty($_item, 'StatusCode', self::DISABLED_STATUS_CODE);
            $tmp->MagentoId = $_item->$_magentoId;
            $tmp->OrderItems = $this->getProperty($_item, 'OrderItems');
            $tmp->Notes = $this->getProperty($_item, 'Notes');
            $tmp->hasReductionOrder = (property_exists($_item, "Orders")) ? $_item->Orders->size > 0 : false;
            $tmp->Orders = $this->getProperty($_item, 'Orders');

            $returnArray[$tmp->MagentoId] = $tmp;
        }

        return $returnArray;
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

        Mage::getSingleton('tnw_salesforce/tool_log')->saveTrace("Order QUERY:\n$query");
        return $this->getClient()->query($query);
    }
}