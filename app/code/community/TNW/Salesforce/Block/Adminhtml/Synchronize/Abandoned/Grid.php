<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Synchronize_Abandoned_Grid extends Mage_Adminhtml_Block_Report_Shopcart_Abandoned_Grid
{
    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId('tnw_salesforce_abandonedsync_grid');
        $this->setDefaultSort('sf_insync');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('filter');
    }
    /**
     * Prepare grid collection object
     *
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function __prepareCollection()
    {
        if ($this->getCollection()) {

            $this->_preparePage();

            $columnId = $this->getParam($this->getVarNameSort(), $this->_defaultSort);
            $dir = $this->getParam($this->getVarNameDir(), $this->_defaultDir);
            $filter = $this->getParam($this->getVarNameFilter(), null);

            if (is_null($filter)) {
                $filter = $this->_defaultFilter;
            }

            if (is_string($filter)) {
                $data = $this->helper('adminhtml')->prepareFilterString($filter);
                $this->_setFilterValues($data);
            } else if ($filter && is_array($filter)) {
                $this->_setFilterValues($filter);
            } else if (0 !== sizeof($this->_defaultFilter)) {
                $this->_setFilterValues($this->_defaultFilter);
            }

            if (isset($this->_columns[$columnId]) && $this->_columns[$columnId]->getIndex()) {
                $dir = (strtolower($dir) == 'desc') ? 'desc' : 'asc';
                $this->_columns[$columnId]->setDir($dir);
                $this->_setCollectionOrder($this->_columns[$columnId]);
            }

            if (!$this->_isExport) {
                $this->getCollection()->load();
                $this->_afterLoadCollection();
            }
        }

        return $this;
    }

    protected function _prepareCollection()
    {
        $abandonedModel = Mage::getModel('tnw_salesforce/abandoned');
        /** @var $collection Mage_Reports_Model_Resource_Quote_Collection */
        $collection = $abandonedModel->getAbandonedCollection(true);

        $filter = $this->getParam($this->getVarNameFilter(), '');
        $data = $this->helper('adminhtml')->prepareFilterString($filter);

        $collection->addFieldToSelect(array(
            'entity_id',
            'store_id',
            'items_count',
            'items_qty',
            'subtotal',
            'coupon_code',
            'created_at',
            'updated_at',
            'remote_ip',
            'salesforce_id',
            'sf_insync',
            'customer_id',
        ));

        $collection->addSubtotal($this->_storeIds, $data)
            ->addCustomerData($data);

        $from = $collection->getSelect()->getPart(Zend_Db_Select::FROM);
        if (isset($from['cust_mname']['joinType'])) {
            $from['cust_mname']['joinType'] = Zend_Db_Select::LEFT_JOIN;
            $collection->getSelect()->setPart(Zend_Db_Select::FROM, $from);
        }

        if (is_array($this->_storeIds) && !empty($this->_storeIds)) {
            $collection->addFieldToFilter('store_id', array('in' => $this->_storeIds));
        }

        $this->setCollection($collection);
        return $this->__prepareCollection();
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

        $this->addColumn('entity_id', array(
            'header' => Mage::helper('sales')->__('Quote ID'),
            'index' => 'entity_id',
            'filter_index' => 'main_table.entity_id',
            'type' => 'action',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Entity(),
            'actions' => array(
                array(
                    'url' => array(
                        'base' => '*/customer/edit',
                        'params' => array(
                            'active_tab' => 'cart'
                        )
                    ),
                    'field' => 'id',
                    'getter' => 'getCustomerId'
                )
            ),
        ));

        $this->addColumn('salesforce_id', array(
            'header' => Mage::helper('sales')->__('Opportunity ID'),
            'index' => 'salesforce_id',
            'type' => 'text',
            'width' => '140px',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Id(),
        ));


        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', array(
                'header' => Mage::helper('sales')->__('Purchased From (Store)'),
                'index' => 'store_id',
                'type' => 'store',
                'store_view' => true,
            ));
        }

        $this->addColumn('customer_name', array(
            'header' => Mage::helper('reports')->__('Customer Name'),
            'index' => 'customer_name',
        ));

        $this->addColumn('email', array(
            'header' => Mage::helper('reports')->__('Email'),
            'index' => 'email',
            'sortable' => false
        ));

        $this->addColumn('items_count', array(
            'header' => Mage::helper('reports')->__('Number of Items'),
            'width' => '80px',
            'align' => 'right',
            'index' => 'items_count',
            'type' => 'number'
        ));

        $this->addColumn('items_qty', array(
            'header' => Mage::helper('reports')->__('Quantity of Items'),
            'width' => '80px',
            'align' => 'right',
            'index' => 'items_qty',
            'filter_index' => 'main_table.items_count',
            'type' => 'number'
        ));

        if ($this->getRequest()->getParam('website')) {
            $storeIds = Mage::app()->getWebsite($this->getRequest()->getParam('website'))->getStoreIds();
        } else if ($this->getRequest()->getParam('group')) {
            $storeIds = Mage::app()->getGroup($this->getRequest()->getParam('group'))->getStoreIds();
        } else if ($this->getRequest()->getParam('store')) {
            $storeIds = array((int)$this->getRequest()->getParam('store'));
        } else {
            $storeIds = array();
        }
        $this->setStoreIds($storeIds);
        $currencyCode = $this->getCurrentCurrencyCode();

        $this->addColumn('subtotal', array(
            'header' => Mage::helper('reports')->__('Subtotal'),
            'width' => '80px',
            'type' => 'currency',
            'currency_code' => $currencyCode,
            'index' => 'subtotal',
            'renderer' => 'adminhtml/report_grid_column_renderer_currency',
            'rate' => $this->getRate($currencyCode),
        ));


        $this->addColumn('created_at', array(
            'header' => Mage::helper('reports')->__('Created At'),
            'width' => '170px',
            'type' => 'datetime',
            'index' => 'created_at',
            'filter_index' => 'main_table.created_at',
            'sortable' => false
        ));

        $this->addColumn('updated_at', array(
            'header' => Mage::helper('reports')->__('Updated At'),
            'width' => '170px',
            'type' => 'datetime',
            'index' => 'updated_at',
            'filter_index' => 'main_table.updated_at',
            'sortable' => false
        ));

        $this->addColumn('singleAction',
            array(
                'header' => Mage::helper('sales')->__('Action'),
                'width' => '50px',
                'type' => 'action',
                'getter' => 'getId',
                'actions' => array(
                    array(
                        'caption' => Mage::helper('sales')->__('Sync'),
                        'url' => array('base' => '*/*/sync'),
                        'field' => 'abandoned_id'
                    )
                ),
                'filter' => false,
                'sortable' => false,
                'index' => 'stores',
                'is_system' => true,
            )
        );


    }

    protected function _prepareMassaction()
    {
        $this
            ->setMassactionIdField('entity_id')
            ->setMassactionIdFilter('main_table.entity_id')
        ;
        $this->getMassactionBlock()->setFormFieldName('abandoneds');

        if (Mage::helper('tnw_salesforce')->getMagentoVersion() > 1500) {
            $this->getMassactionBlock()->addItem(
                'sync', array(
                    'label' => Mage::helper('tnw_salesforce')->__('Full Synchronization'),
                    'url' => $this->getUrl('*/*/massSyncForce'),
                    'confirm' => Mage::helper('tnw_salesforce')->__('This may duplicate any existing Opportunity Products / Notes / Contact Roles. For best results please delete existing Opportunity for this abandoned in Salesforce!')
                )
            );
        }


        return $this;
    }

    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }

    public function getRowUrl($row)
    {
        return false;
    }

}
