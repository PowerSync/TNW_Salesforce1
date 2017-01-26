<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Synchronize_Wishlist_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    protected $_allowedCustomerGroups = array();

    public function __construct()
    {
        parent::__construct();
        $this->setId('wishlistGrid');
        $this->setUseAjax(true);
        //$this->setDefaultSort('sf_insync');
        //$this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        /** @var Mage_Customer_Model_Resource_Customer $productResource */
        $productResource = Mage::getResourceModel('customer/customer');

        $firstName = $productResource->getAttribute('firstname');
        $lastName  = $productResource->getAttribute('lastname');

        /** @var Mage_Wishlist_Model_Resource_Wishlist_Collection $collection */
        $collection = Mage::getResourceModel('wishlist/wishlist_collection');

        $collection->addFieldToFilter('visibility', 1);
        $collection->getSelect()
            ->columns(array('customer_name' => new Zend_Db_Expr('CONCAT(first.value, \' \', last.value)')))
            ->joinInner(array('first' => $firstName->getBackendTable()), "first.attribute_id = {$firstName->getAttributeId()} AND first.entity_id = main_table.customer_id", array())
            ->joinInner(array('last' => $lastName->getBackendTable()), "last.attribute_id = {$lastName->getAttributeId()} AND last.entity_id = main_table.customer_id", array());

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
            'renderer' => 'tnw_salesforce/adminhtml_renderer_entity_status'
        ));

        $this->addColumn('entity_id', array(
            'header' => Mage::helper('customer')->__('ID'),
            'width' => '50px',
            'index' => 'wishlist_id',
            'type' => 'number',
            'renderer' => 'tnw_salesforce/adminhtml_renderer_link_entity',
            'actions' => array(
                array(
                    'url' => array(
                        'base' => '*/customer/edit',
                        'params' => array(
                            'active_tab' => 'wishlist'
                        )
                    ),
                    'field' => 'id',
                    'getter' => 'getCustomerId',
                )
            ),
        ));

        $this->addColumn('name', array(
            'header' => Mage::helper('sales')->__('Name'),
            'index' => 'name',
            'type' => 'varchar',
        ));

        $this->addColumn('customer_name', array(
            'header' => Mage::helper('sales')->__('Customer Name'),
            'index' => 'customer_name',
            'type' => 'varchar',
            'filter' => false,
            'sortable' => false,
        ));

        $this->addColumn('salesforce_id', array(
            'header' => Mage::helper('sales')->__('Salesforce ID'),
            'index' => 'salesforce_id',
            'type' => 'varchar',
            'width' => '140px',
            'renderer' => 'tnw_salesforce/adminhtml_renderer_link_salesforce_id',
        ));

        $this->addColumn('updated_at', array(
            'header' => Mage::helper('sales')->__('Updated At'),
            'index' => 'updated_at',
            'type' => 'datetime',
            'width' => '200px',
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
                    'field' => 'wishlist_id'
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
            ;
            $this->getMassactionBlock()->setFormFieldName('customers');

            $this->getMassactionBlock()->addItem('sync', array(
                'label' => Mage::helper('tnw_salesforce')->__('Synchronize'),
                'url' => $this->getUrl('*/*/massSync'),
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