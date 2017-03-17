<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Sales_Order_Status_Grid extends Mage_Adminhtml_Block_Sales_Order_Status_Grid
{
    /**
     * @return $this
     */
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('sales/order_status_collection');
        $collection
            ->joinStates();

        $tableName = Mage::helper('tnw_salesforce')
            ->getTable('tnw_salesforce/order_status');

        /** @var Magento_Db_Adapter_Pdo_Mysql $_read */
        $_read    = Mage::helper('tnw_salesforce')->getDbConnection('read');
        $_results = $_read->describeTable($tableName);
        $_fieldsToSelect = array();
        foreach($_results as $_result) {
            if ($_result['COLUMN_NAME'] == 'status') {
                continue;
            }

            $_fieldsToSelect[] = 'tnw.' . $_result['COLUMN_NAME'];
        }

        $collection
            ->getSelect()
            ->joinLeft(
                array('tnw' => $tableName),
                'tnw.status = state_table.status',
                $_fieldsToSelect
            );

        $_return = parent::_prepareCollection();
        $this->setCollection($collection);

        return $_return;
    }

    /**
     * @return $this
     */
    protected function _prepareColumns()
    {
        if (Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()) {
            $this->addColumnAfter('sf_order_status', array(
                'header' => $this->__('Order Status'),
                'type' => 'text',
                'index' => 'sf_order_status',
                'width' => '200px',
                'sortable' => false,
                'filter' => false,
            ), 'status');
        }

        if (Mage::helper('tnw_salesforce/config_sales')->integrationOpportunityAllowed()) {
            $this->addColumnAfter('sf_opportunity_status_code', array(
                'header' => $this->__('Opportunity StageName'),
                'type' => 'text',
                'index' => 'sf_opportunity_status_code',
                'width' => '200px',
                'sortable' => false,
                'filter' => false,
            ), 'status');
        }

        return parent::_prepareColumns();
    }
}