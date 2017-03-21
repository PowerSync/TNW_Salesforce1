<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Salesforce_Data_Opportunity extends TNW_Salesforce_Helper_Salesforce_Data
{
    /**
     * @param Mage_Sales_Model_Order[] $orders
     * @return array|bool
     */
    public function lookup(array $orders)
    {
        $_results = array();
        foreach (array_chunk($orders, self::UPDATE_LIMIT) as $_orders) {
            $_results[] = $this->_queryOpportunity($_orders);
        }

        $records = $this->mergeRecords($_results);
        if (empty($records)) {
            Mage::getSingleton('tnw_salesforce/tool_log')
                ->saveTrace('Opportunity lookup returned: no results...');

            return array();
        }

        $returnArray = array();
        foreach ($this->assignLookupToEntity($records, $orders) as $item) {
            $return = $this->prepareRecord($item['entity'], $item['record']);
            if (empty($return)) {
                continue;
            }

            $returnArray = array_merge($returnArray, $return);
        }

        return $returnArray;
    }

    /**
     * @param Mage_Sales_Model_Order[] $orders
     * @return array|stdClass
     */
    protected function _queryOpportunity(array $orders)
    {
        $columns = $this->columnsLookupQuery();
        $conditions = $this->conditionsLookupQuery($orders);

        $query = sprintf('SELECT %s FROM Opportunity WHERE %s',
            $this->generateLookupSelect($columns),
            $this->generateLookupWhere($conditions));

        Mage::getSingleton('tnw_salesforce/tool_log')
            ->saveTrace("Contact QUERY:\n{$query}");

        return $this->getClient()->query($query);
    }

    /**
     * @return array
     */
    protected function columnsLookupQuery()
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';
        return array(
            'ID',
            'AccountId',
            'Pricebook2Id',
            'OwnerId',
            'StageName',
            $_magentoId,
            '(SELECT Id, ContactId, Role FROM OpportunityContactRoles)',
            '(SELECT Id, Quantity, ServiceDate, UnitPrice, PricebookEntry.ProductCode, PricebookEntry.Product2Id, PricebookEntryId, Description, PricebookEntry.UnitPrice, PricebookEntry.Name FROM OpportunityLineItems)',
            '(SELECT Id, Title, Body FROM Notes)'
        );
    }

    /**
     * @param Mage_Sales_Model_Order[] $orders
     * @return mixed
     */
    protected function conditionsLookupQuery(array $orders)
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

        $conditions = array();
        foreach ($orders as $order) {
            $conditions['OR'][$_magentoId]['IN'][] = $order->getIncrementId();
        }

        foreach ($orders as $order) {
            $quote = Mage::getModel('sales/quote')->load($order->getQuoteId());
            $salesforceId = $quote->getData('salesforce_id');
            if (!empty($salesforceId)) {
                $conditions['OR']['Id']['IN'][] = $salesforceId;
            }
        }

        foreach ($orders as $order) {
            $quote = Mage::getModel('qquoteadv/qqadvcustomer')->load($order->getData('c2q_internal_quote_id'));
            $salesforceId = $quote->getData('salesforce_id');
            if (!empty($salesforceId)) {
                $conditions['OR']['Id']['IN'][] = $salesforceId;
            }
        }

        return $conditions;
    }

    /**
     * @param array $records
     * @return array
     */
    protected function collectLookupIndex(array $records)
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

        $searchIndex = array();
        foreach ($records as $key => $record) {
            // Index Email
            $searchIndex['magentoId'][$key] = null;
            if (!empty($record->$_magentoId)) {
                $searchIndex['magentoId'][$key] = $record->$_magentoId;
            }
        }

        return $searchIndex;
    }

    /**
     * @param array $searchIndex
     * @param Mage_Sales_Model_Order $entity
     * @return array[]
     */
    protected function searchLookupPriorityOrder(array $searchIndex, $entity)
    {
        $recordsIds = array();

        // Priority 1
        $recordsIds[10] = array_keys($searchIndex['magentoId'], $entity->getIncrementId());

        return $recordsIds;
    }

    /**
     * @param array[] $recordsPriority
     * @param Mage_Customer_Model_Customer $entity
     * @return stdClass|null
     */
    protected function filterLookupByPriority(array $recordsPriority, $entity)
    {

    }

    /**
     * @param $customer Mage_Sales_Model_Order
     * @param $record stdClass
     * @return array
     */
    public function prepareRecord($customer, $record)
    {
        $_magentoId = Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c';

        $tmp = new stdClass();
        $tmp->Id = $record->Id;
        $tmp->AccountId = $this->getProperty($record, 'AccountId');
        $tmp->Pricebook2Id = $this->getProperty($record, 'Pricebook2Id');
        $tmp->MagentoId = $this->getProperty($record, $_magentoId);
        $tmp->OpportunityContactRoles = $this->getProperty($record, 'OpportunityContactRoles');
        $tmp->OpportunityLineItems = $this->getProperty($record, 'OpportunityLineItems');
        $tmp->Notes = $this->getProperty($record, 'Notes');
        $tmp->OwnerId = $this->getProperty($record, 'OwnerId');
        $tmp->StageName = $this->getProperty($record, 'StageName');

        return array($customer->getIncrementId() => $tmp);
    }
}