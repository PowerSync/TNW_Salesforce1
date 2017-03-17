<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Synchronize_Order_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    protected $_allowedOrderStatuses = array();
    protected $_allowedCustomerGroups = array();
    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('tnw_salesforce_ordersync_grid');
        $this->setDefaultSort('sf_insync');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('filter');
    }

    protected function _getCollectionClass()
    {
        return 'sales/order_grid_collection';
    }

    protected function _prepareCollection()
    {
        if (!Mage::helper('tnw_salesforce')->syncAllOrders()) {
            $this->_allowedOrderStatuses = explode(',', Mage::helper('tnw_salesforce')->getAllowedOrderStates());
        }
        if (!Mage::helper('tnw_salesforce')->getSyncAllGroups()) {
            $this->_allowedCustomerGroups = Mage::helper('tnw_salesforce')->getAllowedCustomerGroups();
        }

        $collection = Mage::getResourceModel($this->_getCollectionClass());
        $collection->getSelect()->join(
            array('flat_order' => Mage::helper('tnw_salesforce')->getTable('sales_flat_order')),
            'main_table.entity_id = flat_order.entity_id',
            array('salesforce_id', 'opportunity_id', 'sf_insync')
        );
        $collection->addFieldToFilter('main_table.increment_id', array('notnull' => true));
        if (!empty($this->_allowedOrderStatuses)) {
            $collection->addFieldToFilter('main_table.status', array('in' => $this->_allowedOrderStatuses));
        }
        if (!empty($this->_allowedCustomerGroups)) {
            $collection->addFieldToFilter('flat_order.customer_group_id', array('in' => $this->_allowedCustomerGroups));
        }
        $collection->addAttributeToSelect('increment_id');
        $collection->addAttributeToSelect('created_at');
        $collection->addAttributeToSelect('grand_total');
        $collection->addAttributeToSelect('status');
        $collection->addAttributeToSelect('entity_id');
        $collection->addAttributeToSelect('billing_name');
        $collection->addAttributeToSelect('shipping_name');
        if (!Mage::app()->isSingleStoreMode()) {
            $collection->addAttributeToSelect('store_id');
        }
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

        $this->addColumn('real_order_id', array(
            'header' => Mage::helper('sales')->__('Order #'),
            'width' => '80px',
            'type' => 'action',
            'index' => 'increment_id',
            'filter_index' => 'main_table.increment_id',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Entity(),
            'actions' => array(
                array(
                    'url' => array('base' => '*/sales_order/view'),
                    'field' => 'order_id',
                    'getter' => 'getId',
                )
            ),
        ));

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', array(
                'header' => Mage::helper('sales')->__('Purchased From (Store)'),
                'index' => 'store_id',
                'type' => 'store',
                'store_view' => true,
                'display_deleted' => true,
                'filter_index' => 'main_table.store_id',
            ));
        }

        $this->addColumn('billing_name', array(
            'header' => Mage::helper('sales')->__('Bill to Name'),
            'index' => 'billing_name',
        ));

        $this->addColumn('shipping_name', array(
            'header' => Mage::helper('sales')->__('Ship to Name'),
            'index' => 'shipping_name',
        ));

        $this->addColumn('created_at', array(
            'header' => Mage::helper('sales')->__('Purchased On'),
            'index' => 'created_at',
            'type' => 'datetime',
            'width' => '200px',
            'filter_index' => 'main_table.created_at',
        ));

        if (Mage::helper('tnw_salesforce/config_sales')->integrationOrderAllowed()) {
            $this->addColumn('salesforce_id', array(
                'header' => Mage::helper('sales')->__('Order ID'),
                'index' => 'salesforce_id',
                'type' => 'text',
                'width' => '140px',
                'renderer' => 'tnw_salesforce/adminhtml_renderer_link_salesforce_id',
            ));
        }

        if (Mage::helper('tnw_salesforce/config_sales')->integrationOpportunityAllowed()) {
            $this->addColumn('opportunity_id', array(
                'header' => Mage::helper('sales')->__('Opportunity ID'),
                'index' => 'opportunity_id',
                'type' => 'text',
                'width' => '140px',
                'renderer' => 'tnw_salesforce/adminhtml_renderer_link_salesforce_id',
            ));
        }

        $this->addColumn('grand_total', array(
            'header' => Mage::helper('sales')->__('G.T. (Purchased)'),
            'index' => 'grand_total',
            'type' => 'currency',
            'currency' => 'order_currency_code',
            'filter_index' => 'main_table.grand_total',
        ));

        $this->addColumn('status', array(
            'header' => Mage::helper('sales')->__('Order Status'),
            'index' => 'status',
            'type'  => 'options',
            'width' => '70px',
            'options' => Mage::getSingleton('sales/order_config')->getStatuses(),
            'filter_index' => 'main_table.status',
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
        if (Mage::helper('tnw_salesforce')->isProfessionalEdition()) {
            $this
                ->setMassactionIdField('entity_id')
                ->setMassactionIdFilter('main_table.entity_id')
            ;
            $this->getMassactionBlock()->setFormFieldName('orders');

            if (Mage::helper('tnw_salesforce')->getMagentoVersion() > 1500) {
                $this->getMassactionBlock()->addItem(
                    'sync', array(
                        'label' => Mage::helper('tnw_salesforce')->__('Full Synchronization'),
                        'url' => $this->getUrl('*/*/massSyncForce'),
                        'confirm' => Mage::helper('tnw_salesforce')->__('This may duplicate any existing Opportunity Products / Notes / Contact Roles. For best results please delete existing Opportunity for this order in Salesforce!')
                    )
                );
            }
//        $this->getMassactionBlock()->addItem(
//            'forceSync', array(
//                'label' => Mage::helper('tnw_salesforce')->__('Synchronize Shopping Carts'),
//                'url' => $this->getUrl('*/*/massCartSync'),
//                'confirm' => Mage::helper('tnw_salesforce')->__('Please make sure Opportunity for this order already exists in Salesforce. For best results we suggest you remove all Opportunity Products from Orders you about to synchronize!')
//            )
//        );

//        $this->getMassactionBlock()->addItem(
//            'forceSync', array(
//                'label' => Mage::helper('tnw_salesforce')->__('Synchronize Order Notes'),
//                'url' => $this->getUrl('*/*/massNotesSync'),
//                'confirm' => Mage::helper('tnw_salesforce')->__('Please make sure Opportunity for this order already exists in Salesforce. For best results we suggest you remove all Notes from Orders you about to synchronize!')
//            )
//        );
        }
        return $this;
    }

    /**
     * @param $item
     * @return string
     */
    public function getRowUrl($item)
    {
        return '';
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}
