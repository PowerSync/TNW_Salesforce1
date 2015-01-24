<?php

/**
 * Class TNW_Salesforce_Block_Sales_Order_Status_Grid
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
        $collection
            ->getSelect()
            ->joinLeft(
                array('tnw' => Mage::helper('tnw_salesforce')->getTable('tnw_salesforce_order_status')),
                'tnw.status = state_table.status',
                array('tnw.sf_opportunity_status_code','tnw.sf_order_status')
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
        $tmp = parent::_prepareColumns();
        $this->addColumn('sf_opportunity_status_code', array(
            'header' => Mage::helper('sales')->__('SF Opportunity StageName'),
            'type' => 'text',
            'index' => 'sf_opportunity_status_code',
            'width' => '200px',
            'sortable' => false,
            'filter' => false,
        ));

        return $tmp;
    }
}