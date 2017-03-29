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
            if (empty($item['record'])) {
                continue;
            }

            $return = $this->prepareRecord($item['entity'], $item['record']);
            if (empty($return)) {
                continue;
            }

            list($entityNumber, $record) = each($return);
            $returnArray[$entityNumber] = $record;
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
            ->saveTrace("Opportunity QUERY:\n{$query}");

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

        $conditions = $orderIndex = array();
        foreach ($orders as $order) {
            $conditions['OR'][$_magentoId]['IN'][] = $order->getIncrementId();
            $orderIndex[$order->getQuoteId()] = $order;
        }

        /** @var Mage_Sales_Model_Resource_Quote_Collection $collection */
        $collection = Mage::getResourceModel('sales/quote_collection');
        $collection
            ->addFieldToFilter($collection->getResource()->getIdFieldName(), array('in'=> array_keys($orderIndex)))
            ->addFieldToFilter('salesforce_id', array('notnull'=>true))
        ;

        /** @var Mage_Sales_Model_Quote $quote */
        foreach ($collection as $quote) {
            $orderIndex[$quote->getId()]->setData('_quote_salesforce_id', $quote->getData('salesforce_id'));
            $conditions['OR']['Id']['IN'][] = $quote->getData('salesforce_id');
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

            $searchIndex['salesforceId'][$key] = null;
            if (!empty($record->Id)) {
                $searchIndex['salesforceId'][$key] = $record->Id;
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

        $recordsIds[10] = array_keys($searchIndex['magentoId'], $entity->getIncrementId());
        $recordsIds[20] = array_keys($searchIndex['salesforceId'], $entity->getData('_quote_salesforce_id'));

        return $recordsIds;
    }

    /**
     * @param array[] $recordsPriority
     * @param Mage_Customer_Model_Customer $entity
     * @return stdClass|null
     */
    protected function filterLookupByPriority(array $recordsPriority, $entity)
    {
        $findRecord = null;
        foreach ($recordsPriority as $records) {
            if (empty($records)) {
                continue;
            }

            $findRecord = reset($records);
            break;
        }

        return $findRecord;
    }

    /**
     * @param $customer Mage_Sales_Model_Order
     * @param $record stdClass
     * @return array
     */
    public function prepareRecord($customer, $record)
    {
        $tmp = new stdClass();
        $tmp->Id = $record->Id;
        $tmp->AccountId = $this->getProperty($record, 'AccountId');
        $tmp->Pricebook2Id = $this->getProperty($record, 'Pricebook2Id');
        $tmp->MagentoId = $this->getProperty($record, Mage::helper('tnw_salesforce/config')->getSalesforcePrefix() . 'Magento_ID__c');
        $tmp->OpportunityContactRoles = $this->getProperty($record, 'OpportunityContactRoles');
        $tmp->OpportunityLineItems = $this->getProperty($record, 'OpportunityLineItems');
        $tmp->Notes = $this->getProperty($record, 'Notes');
        $tmp->OwnerId = $this->getProperty($record, 'OwnerId');
        $tmp->StageName = $this->getProperty($record, 'StageName');

        return array($customer->getIncrementId() => $tmp);
    }
}