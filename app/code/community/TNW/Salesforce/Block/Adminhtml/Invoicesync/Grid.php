<?php

class TNW_Salesforce_Block_Adminhtml_Invoicesync_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    protected $_allowedOrderStatuses = array();
    protected $_allowedCustomerGroups = array();
    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('tnw_salesforce_invoicesync_grid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('filter');
    }

    protected function _getCollectionClass()
    {
        return 'sales/order_invoice_grid_collection';
    }

    protected function _prepareCollection()
    {
        /** @var Mage_Sales_Model_Resource_Order_Invoice_Grid_Collection $collection */
        $collection = Mage::getResourceModel($this->_getCollectionClass());
        $collection->getSelect()->join(
            array('flat_invoice' => Mage::helper('tnw_salesforce')->getTable('sales_flat_invoice')),
            'main_table.entity_id = flat_invoice.entity_id',
            array('salesforce_id', 'sf_insync')
        );

        $collection->addAttributeToSelect('entity_id')
            ->addAttributeToSelect('increment_id')
            ->addAttributeToSelect('created_at')
            ->addAttributeToSelect('order_increment_id')
            ->addAttributeToSelect('order_created_at')
            ->addAttributeToSelect('billing_name')
            ->addAttributeToSelect('state')
            ->addAttributeToSelect('grand_total');

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
            'filter_index' => 'flat_invoice.sf_insync',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Status()
        ));

        $this->addColumn('increment_id', array(
            'header'    => Mage::helper('sales')->__('Invoice #'),
            'index'     => 'increment_id',
            'type'      => 'text',
            'filter_index' => 'main_table.increment_id',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Entity(),
            'actions' => array(
                array(
                    'url' => array('base' => '*/sales_invoice/view'),
                    'field' => 'invoice_id',
                    'getter' => 'getId',
                )
            ),
        ));

        $this->addColumn('salesforce_id', array(
            'header' => $this->__('Salesforce ID'),
            'index' => 'salesforce_id',
            'type' => 'text',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Id(),
            'filter_index' => 'flat_invoice.salesforce_id',
        ));

        $this->addColumn('created_at', array(
            'header'    => Mage::helper('sales')->__('Invoice Date'),
            'index'     => 'created_at',
            'type'      => 'datetime',
            'filter_index' => 'main_table.created_at',
        ));

        $this->addColumn('order_increment_id', array(
            'header'    => Mage::helper('sales')->__('Order #'),
            'index'     => 'order_increment_id',
            'type'      => 'text',
            'filter_index' => 'main_table.order_increment_id',
        ));

        $this->addColumn('order_created_at', array(
            'header'    => Mage::helper('sales')->__('Order Date'),
            'index'     => 'order_created_at',
            'type'      => 'datetime',
            'filter_index' => 'main_table.order_created_at',
        ));

        $this->addColumn('billing_name', array(
            'header' => Mage::helper('sales')->__('Bill to Name'),
            'index' => 'billing_name',
            'filter_index' => 'main_table.billing_name',
        ));

        $this->addColumn('state', array(
            'header'    => Mage::helper('sales')->__('Status'),
            'index'     => 'state',
            'type'      => 'options',
            'options'   => Mage::getModel('sales/order_invoice')->getStates(),
            'filter_index' => 'main_table.state',
        ));

        $this->addColumn('grand_total', array(
            'header'    => Mage::helper('customer')->__('Amount'),
            'index'     => 'grand_total',
            'type'      => 'currency',
            'align'     => 'right',
            'currency'  => 'order_currency_code',
            'filter_index' => 'main_table.grand_total',
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
                        'field'   => 'invoice_id'
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
            $this->getMassactionBlock()->setFormFieldName('invoice_ids');
            $this->getMassactionBlock()->setUseSelectAll(false);

            $this->getMassactionBlock()->addItem(
                'sync', array(
                    'label' => Mage::helper('tnw_salesforce')->__('Full Synchronization'),
                    'url' => $this->getUrl('*/*/massSyncForce'),
                    'confirm' => Mage::helper('tnw_salesforce')->__('Are you sure?')
                )
            );
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
