<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Synchronize_Product_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('productGrid');
        $this->setDefaultSort('sf_insync');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
        $this->setVarNameFilter('product_filter');
    }

    /**
     * @return Mage_Core_Model_Store
     */
    protected function _getStore()
    {
        $storeId = (int)$this->getRequest()->getParam('store', 0);

        return Mage::app()->getStore($storeId);
    }

    /**
     * @return $this|Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        $store = $this->_getStore();
        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('salesforce_disable_sync');

        // here magento orm apply condition to left join itself but not to whole result, first example below is our case
        // select * from t1 left join t2 on t2.link = t1.link and t2.value = 12
        // select * from t1 left join t2 on t2.link = t1.link where t2.value = 12
        $attribute = Mage::getModel('eav/entity_attribute')->loadByCode('catalog_product', 'salesforce_disable_sync');
        $collection->getSelect()->joinLeft(
            array('at_salesforce_disable_sync' => Mage::helper('tnw_salesforce')->getTable('catalog_product_entity_int')),
            '`at_salesforce_disable_sync`.`entity_id` = `e`.`entity_id` AND `at_salesforce_disable_sync`.`attribute_id` = "' . $attribute->getId() . '" AND `at_salesforce_disable_sync`.`store_id` = 0',
            array('salesforce_disable_sync' => 'value')
        );
        $collection->getSelect()->where('`at_salesforce_disable_sync`.`value` = 0 OR `at_salesforce_disable_sync`.`value` IS NULL');

        if ($store->getId()) {
            $collection->getSelect()->columns(array('website_id'=>new Zend_Db_Expr($store->getWebsiteId())));

            $collection->addStoreFilter($store);
            $collection->joinAttribute('name', 'catalog_product/name', 'entity_id', null, 'inner', Mage_Core_Model_App::ADMIN_STORE_ID);
            $collection->joinAttribute('salesforce_id', 'catalog_product/salesforce_id', 'entity_id', null, 'left', $store->getId());
            $collection->joinAttribute('custom_name', 'catalog_product/name', 'entity_id', null, 'inner', $store->getId());
            $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner', $store->getId());
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner', $store->getId());
            $collection->joinAttribute('price', 'catalog_product/price', 'entity_id', null, 'left', $store->getId());
            $collection->joinAttribute('salesforce_pricebook_id', 'catalog_product/salesforce_pricebook_id', 'entity_id', null, 'left', $store->getId());
            $collection->joinAttribute('sf_insync', 'catalog_product/sf_insync', 'entity_id', null, 'left', $store->getId());
        } else {
            $collection->addAttributeToSelect('price');
            $collection->joinAttribute('status', 'catalog_product/status', 'entity_id', null, 'inner');
            $collection->joinAttribute('visibility', 'catalog_product/visibility', 'entity_id', null, 'inner');
            $collection->joinAttribute('salesforce_pricebook_id', 'catalog_product/salesforce_pricebook_id', 'entity_id', null, 'left');
            $collection->joinAttribute('salesforce_id', 'catalog_product/salesforce_id', 'entity_id', null, 'left');
            $collection->joinAttribute('sf_insync', 'catalog_product/sf_insync', 'entity_id', null, 'left');
        }

        $this->setCollection($collection);

        parent::_prepareCollection();
        $this->getCollection()->addWebsiteNamesToResult();

        return $this;
    }

    /**
     * @param $column
     * @return $this
     */
    protected function _addColumnFilterToCollection($column)
    {
        if ($this->getCollection()) {
            if ($column->getId() == 'websites') {
                $this->getCollection()->joinField('websites',
                    'catalog/product_website',
                    'website_id',
                    'product_id=entity_id',
                    null,
                    'left');
            }
        }

        return parent::_addColumnFilterToCollection($column);
    }

    /**
     * @return $this
     */
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
            'renderer' => 'tnw_salesforce/adminhtml_renderer_entity_status'
        ));

        $this->addColumn('entity_id',
            array(
                'header' => Mage::helper('catalog')->__('ID'),
                'width' => '50px',
                'type' => 'number',
                'index' => 'entity_id',
                'renderer' => 'tnw_salesforce/adminhtml_renderer_link_entity',
                'actions' => array(
                    array(
                        'url' => array('base' => '*/catalog_product/edit'),
                        'field' => 'id',
                        'getter' => 'getId',
                    )
                ),
            ));
        $this->addColumn('name',
            array(
                'header' => Mage::helper('catalog')->__('Name'),
                'index' => 'name',
            ));
        $this->addColumn('salesforce_id', array(
            'header' => Mage::helper('sales')->__('Product2 ID'),
            'index' => 'salesforce_id',
            'type' => 'varchar',
            'width' => '140px',
            'renderer' => 'tnw_salesforce/adminhtml_renderer_link_salesforce_id',
        ));
        $this->addColumn('salesforce_pricebook_id', array(
            'header' => Mage::helper('sales')->__('Product2 Pricebook ID'),
            'index' => 'salesforce_pricebook_id',
            'type' => 'text',
            'width' => '180px',
            'renderer' => 'tnw_salesforce/adminhtml_renderer_link_salesforce_id',
        ));
        $store = $this->_getStore();
        if ($store->getId()) {
            $this->addColumn('custom_name',
                array(
                    'header' => Mage::helper('catalog')->__('Name in %s', $store->getName()),
                    'index' => 'custom_name',
                ));
        }

        $this->addColumn('type',
            array(
                'header' => Mage::helper('catalog')->__('Type'),
                'width' => '150px',
                'index' => 'type_id',
                'type' => 'options',
                'options' => Mage::getSingleton('catalog/product_type')->getOptionArray(),
            ));

        $this->addColumn('sku',
            array(
                'header' => Mage::helper('catalog')->__('SKU'),
                'width' => '80px',
                'index' => 'sku',
            ));

        $store = $this->_getStore();
        $this->addColumn('price',
            array(
                'header' => Mage::helper('catalog')->__('Price'),
                'type' => 'price',
                'currency_code' => $store->getBaseCurrency()->getCode(),
                'index' => 'price',
            ));

        $this->addColumn('status',
            array(
                'header' => Mage::helper('catalog')->__('Status'),
                'width' => '70px',
                'index' => 'status',
                'type' => 'options',
                'options' => Mage::getSingleton('catalog/product_status')->getOptionArray(),
            ));

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('websites',
                array(
                    'header' => Mage::helper('catalog')->__('Websites'),
                    'width' => '100px',
                    'sortable' => false,
                    'index' => 'websites',
                    'type' => 'options',
                    'options' => Mage::getModel('core/website')->getCollection()->toOptionHash(),
                ));
        }

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
                        'field' => 'product_id'
                    )
                ),
                'filter' => false,
                'sortable' => false,
                'index' => 'stores',
                'is_system' => true,
            ));

        return parent::_prepareColumns();
    }

    /**
     * @return $this|Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareMassaction()
    {
        if (Mage::helper('tnw_salesforce')->isProfessionalEdition()) {
            $this
                ->setMassactionIdField('entity_id');
            $this->getMassactionBlock()->setFormFieldName('products');

            $url = '*/*/massSync';
            if (Mage::helper('tnw_salesforce')->getStoreId() != 0) {
                $url .= '/store/' . Mage::helper('tnw_salesforce')->getStoreId();
            }

            $this->getMassactionBlock()->addItem('sync', array(
                'label' => Mage::helper('tnw_salesforce')->__('Synchronize'),
                'url' => $this->getUrl($url),
                'confirm' => Mage::helper('tnw_salesforce')->__('This will ovewrite any mapped data in Salesforce. Are you sure?')
            ));
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

    /**
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}