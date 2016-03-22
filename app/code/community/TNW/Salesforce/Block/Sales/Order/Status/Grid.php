<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Sales_Order_Status_Grid extends Mage_Adminhtml_Block_Sales_Order_Status_Grid
{
    protected $_columnName = 'sf_opportunity_status_code';

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
        $this->addColumnAfter(
            $this->_getSfStatusFieldName(),
            $this->_getSfStatusField(),
            'status'
        );
        return parent::_prepareColumns();
    }

    protected function _getSfStatusField() {
        $_syncObject = strtolower(Mage::app()->getStore(Mage::app()->getStore()->getStoreId())->getConfig(TNW_Salesforce_Helper_Data::ORDER_OBJECT));

        $_field = array(
            'header' => $this->_getHeaderLabel($_syncObject),
            'type' => 'text',
            'index' => $this->_columnName,
            'width' => '200px',
            'sortable' => false,
            'filter' => false,
        );

        return $_field;
    }

    protected function _getHeaderLabel($_syncObject) {
        if ($_syncObject == 'order') {
            $_label = $this->__('Order Status');
            $this->_columnName = 'sf_order_status';
        } else {
            $_label = $this->__('Opportunity StageName');
            $this->_columnName = 'sf_opportunity_status_code';
        }

        return $_label;
    }

    protected function _getSfStatusFieldName() {
        return $this->_columnName;
    }

    protected function setSfStatusFieldName($_name) {
        $this->_columnName = $_name;
    }
}