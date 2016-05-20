<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Creditmemosync_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('tnw_salesforce_creditmemosync_grid');
        $this->setDefaultSort('sf_insync');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('filter');
    }

    protected function _getCollectionClass()
    {
        return 'sales/order_creditmemo_grid_collection';
    }

    protected function _prepareCollection()
    {
        /** @var Mage_Sales_Model_Resource_Order_Creditmemo_Grid_Collection $collection */
        $collection = Mage::getResourceModel($this->_getCollectionClass());
        $collection->getSelect()->join(
            array('flat_order' => Mage::helper('tnw_salesforce')->getTable('sales_flat_creditmemo')),
            'main_table.entity_id = flat_order.entity_id',
            array('salesforce_id', 'sf_insync')
        );

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('sf_insync', array(
            'header' => Mage::helper('sales')->__('Status'),
            'width' => '40px',
            'type' => 'options',
            'options' => array(
                0 => 'No',
                1 => 'Yes',
            ),
            'index' => 'sf_insync',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Status()
        ));

        $this->addColumn('increment_id', array(
            'header'    => Mage::helper('sales')->__('Credit Memo #'),
            'index'     => 'increment_id',
            'type'      => 'text',
            'renderer'  => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Entity(),
            'actions'   => array(
                array(
                    'url' => array('base' => '*/sales_creditmemo/view'),
                    'field' => 'creditmemo_id',
                    'getter' => 'getId',
                )
            ),
        ));

        $this->addColumn('created_at', array(
            'header'    => Mage::helper('sales')->__('Created At'),
            'index'     => 'created_at',
            'type'      => 'datetime',
        ));

        $this->addColumn('order_increment_id', array(
            'header'    => Mage::helper('sales')->__('Order #'),
            'index'     => 'order_increment_id',
            'type'      => 'text',
        ));

        $this->addColumn('order_created_at', array(
            'header'    => Mage::helper('sales')->__('Order Date'),
            'index'     => 'order_created_at',
            'type'      => 'datetime',
        ));

        $this->addColumn('billing_name', array(
            'header' => Mage::helper('sales')->__('Bill to Name'),
            'index' => 'billing_name',
        ));

        $this->addColumn('salesforce_id', array(
            'header' => Mage::helper('sales')->__('Salesforce ID'),
            'index' => 'salesforce_id',
            'type' => 'text',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Id(),
        ));

        $this->addColumn('state', array(
            'header'    => Mage::helper('sales')->__('Status'),
            'index'     => 'state',
            'type'      => 'options',
            'options'   => Mage::getModel('sales/order_creditmemo')->getStates(),
        ));

        $this->addColumn('grand_total', array(
            'header'    => Mage::helper('customer')->__('Refunded'),
            'index'     => 'grand_total',
            'type'      => 'currency',
            'align'     => 'right',
            'currency'  => 'order_currency_code',
        ));

        $this->addColumn('singleAction', array(
                'header' => Mage::helper('sales')->__('Action'),
                'width' => '50px',
                'type' => 'action',
                'getter' => 'getId',
                'actions' => array(
                    array(
                        'caption' => Mage::helper('sales')->__('Sync'),
                        'url' => array('base' => '*/*/sync'),
                        'field' => 'order_id'
                    )
                ),
                'filter' => false,
                'sortable' => false,
                'index' => 'stores',
                'is_system' => true,
        ));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $this->setMassactionIdField('mapping_id');
            $this->getMassactionBlock()->setFormFieldName('creditmemo');

            if (Mage::helper('tnw_salesforce')->getMagentoVersion() > 1500) {
                $this->getMassactionBlock()->addItem(
                    'sync', array(
                        'label' => Mage::helper('tnw_salesforce')->__('Full Synchronization'),
                        'url' => $this->getUrl('*/*/massSyncForce'),
                        'confirm' => Mage::helper('tnw_salesforce')->__('This may duplicate any existing Opportunity Products / Notes / Contact Roles. For best results please delete existing Opportunity for this order in Salesforce!')
                    )
                );
            }
        }
        return $this;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}
