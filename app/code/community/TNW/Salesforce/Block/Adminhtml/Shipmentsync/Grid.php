<?php

class TNW_Salesforce_Block_Adminhtml_Shipmentsync_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    protected $_allowedOrderStatuses = array();
    protected $_allowedCustomerGroups = array();
    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('tnw_salesforce_shipmentsync_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('filter');
    }

    protected function _getCollectionClass()
    {
        return 'sales/order_shipment_grid_collection';
    }

    protected function _prepareCollection()
    {
        /** @var Mage_Sales_Model_Resource_Order_Shipment_Grid_Collection $collection */
        $collection = Mage::getResourceModel($this->_getCollectionClass());
        $collection->getSelect()->join(
            array('flat_shipment' => Mage::helper('tnw_salesforce')->getTable('sales_flat_shipment')),
            'main_table.entity_id = flat_shipment.entity_id',
            array('salesforce_id', 'sf_insync')
        );

        $collection->addAttributeToSelect('entity_id')
            ->addAttributeToSelect('increment_id')
            ->addAttributeToSelect('created_at')
            ->addAttributeToSelect('order_increment_id')
            ->addAttributeToSelect('order_created_at')
            ->addAttributeToSelect('shipping_name')
            ->addAttributeToSelect('total_qty');

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('sf_insync', array(
            'header' => Mage::helper('sales')->__('In Sync'),
            'width' => '40px',
            'type' => 'options',
            'options' => array(
                0 => 'No',
                1 => 'Yes',
            ),
            'index' => 'sf_insync',
            'filter_index' => 'flat_shipment.sf_insync',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Status()
        ));

        $this->addColumn('increment_id', array(
            'header'    => Mage::helper('sales')->__('Shipment #'),
            'index'     => 'increment_id',
            'type'      => 'text',
            'filter_index' => 'main_table.increment_id',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Entity(),
            'actions' => array(
                array(
                    'url' => array('base' => '*/sales_shipment/view'),
                    'field' => 'shipment_id',
                    'getter' => 'getId',
                )
            ),
        ));

        $this->addColumn('salesforce_id', array(
            'header' => $this->__('Salesforce ID'),
            'index' => 'salesforce_id',
            'type' => 'text',
            'width' => '140px',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Id(),
            'filter_index' => 'flat_shipment.salesforce_id',
        ));

        $this->addColumn('created_at', array(
            'header'    => Mage::helper('sales')->__('Date Shipped'),
            'index'     => 'created_at',
            'filter_index' => 'main_table.created_at',
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

        $this->addColumn('shipping_name', array(
            'header' => Mage::helper('sales')->__('Ship to Name'),
            'index' => 'shipping_name',
        ));

        $this->addColumn('total_qty', array(
            'header' => Mage::helper('sales')->__('Total Qty'),
            'index' => 'total_qty',
            'filter_index' => 'main_table.total_qty',
            'type'  => 'number',
        ));

        $this->addColumn('action',
            array(
                'header'    => Mage::helper('sales')->__('Action'),
                'width'     => '50px',
                'type'      => 'action',
                'getter'     => 'getId',
                'actions'   => array(
                    array(
                        'caption' => $this->__('Sync'),
                        'url'     => array('base'=>'*/*/sync'),
                        'field'   => 'shipment_id'
                    )
                ),
                'filter'    => false,
                'sortable'  => false,
                'is_system' => true
            ));

        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        if (Mage::helper('tnw_salesforce')->getType() == "PRO") {
            $this->setMassactionIdField('entity_id');
            $this->getMassactionBlock()->setFormFieldName('shipment_ids');

            $this->getMassactionBlock()->addItem('sync', array(
                'label' => Mage::helper('tnw_salesforce')->__('Full Synchronization'),
                'url' => $this->getUrl('*/*/massSyncForce'),
                'confirm' => Mage::helper('tnw_salesforce')->__('Are you sure?')
            ));
        }

        return $this;
    }

    public function getRowUrl($row)
    {
        return false;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}
