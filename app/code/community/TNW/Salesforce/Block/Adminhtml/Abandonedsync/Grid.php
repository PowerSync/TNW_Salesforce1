<?php

class TNW_Salesforce_Block_Adminhtml_Abandonedsync_Grid extends Mage_Adminhtml_Block_Report_Shopcart_Abandoned_Grid
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
        /** @var $collection Mage_Reports_Model_Resource_Quote_Collection */
        $collection = Mage::getResourceModel('reports/quote_collection');

        $filter = $this->getParam($this->getVarNameFilter(), array());
        if ($filter) {
            $filter = base64_decode($filter);
            parse_str(urldecode($filter), $data);
        } else {
            $filter = null;
        }

        $collection->addFieldToSelect('entity_id');
        $collection->addFieldToSelect('items_count');
        $collection->addFieldToSelect('items_qty');
        $collection->addFieldToSelect('subtotal');
        $collection->addFieldToSelect('coupon_code');
        $collection->addFieldToSelect('created_at');
        $collection->addFieldToSelect('updated_at');
        $collection->addFieldToSelect('remote_ip');

        $collection->addFieldToFilter('items_count', array('neq' => '0'))
            ->addFieldToFilter('main_table.is_active', '1')
            ->addSubtotal($this->_storeIds, $filter)
            ->addCustomerData($filter)
            ->setOrder('main_table.updated_at');


        $collection->addFieldToSelect('sf_insync');
        $collection->addFieldToFilter('main_table.created_at', array('lt' => Mage::helper('tnw_salesforce/abandoned')->getDateLimit()->toString(Varien_Date::DATETIME_INTERNAL_FORMAT)));


        if (is_array($this->_storeIds) && !empty($this->_storeIds)) {
            $this->addFieldToFilter('store_id', array('in' => $this->_storeIds));
        }

        $this->setCollection($collection);
        return $this->__prepareCollection();
    }


    /**
     * Add new export type to grid
     *
     * @param   string $url
     * @param   string $label
     * @return  Mage_Adminhtml_Block_Widget_Grid
     */
    public function addExportType($url, $label)
    {
//        $this->_exportTypes[] = new Varien_Object(
//            array(
//                'url'   => $this->getUrl($url, array('_current'=>true)),
//                'label' => $label
//            )
//        );
        return $this;
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
            'type' => 'text',
        ));

        $this->addColumn('salesforce_id', array(
            'header' => Mage::helper('sales')->__('Opportunity ID'),
            'index' => 'salesforce_id',
            'filter_index' => 'main_table.salesforce_id',
            'type' => 'text',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Id(),
        ));


        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('store_id', array(
                'header' => Mage::helper('sales')->__('Purchased From (Store)'),
                'index' => 'main_table.store_id',
                'type' => 'store',
                'store_view' => true,
                'display_deleted' => true,
                'filter_index' => '`main_table`.`store_id`',
            ));
        }

        $this->addColumn('customer_name', array(
            'header' => Mage::helper('reports')->__('Customer Name'),
            'index' => 'customer_name',
            'filter_index' => 'main_table.customer_name',
            
        ));

        $this->addColumn('email', array(
            'header' => Mage::helper('reports')->__('Email'),
            'index' => 'email',
            'filter_index' => 'main_table.email',
            'sortable' => false
        ));

        $this->addColumn('items_count', array(
            'header' => Mage::helper('reports')->__('Number of Items'),
            'width' => '80px',
            'align' => 'right',
            'index' => 'items_count',
            'filter_index' => 'main_table.items_count',
            
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

        $this->addColumn('coupon_code', array(
            'header' => Mage::helper('reports')->__('Applied Coupon'),
            'width' => '80px',
            'index' => 'coupon_code',
            'sortable' => false
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

        $this->addColumn('remote_ip', array(
            'header' => Mage::helper('reports')->__('IP Address'),
            'width' => '80px',
            'index' => 'remote_ip',
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
                
                'index' => 'stores',
                'is_system' => true,
            )
        );


    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('entity_id');
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
}
