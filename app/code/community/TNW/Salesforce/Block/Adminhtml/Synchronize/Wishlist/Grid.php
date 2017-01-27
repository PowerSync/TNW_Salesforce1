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
        /** @var Mage_Customer_Model_Resource_Customer_Collection $collection */
        $collection = Mage::getResourceModel('customer/customer_collection');
        $collection
            ->addNameToSelect()
            ->joinTable(
                array('wishlist'=>'wishlist/wishlist'),
                'customer_id=entity_id',
                array('wishlist_id', 'wishlist_name'=>'name', 'wishlist_updated_at'=>'updated_at'),
                array('visibility'=>'1')
            )
        ;

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

        $this->addColumn('wishlist_id', array(
            'header' => Mage::helper('wishlist')->__('ID'),
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
                    'getter' => 'getId',
                )
            ),
        ));

        $this->addColumn('name', array(
            'header' => Mage::helper('sales')->__('Name'),
            'index' => 'wishlist_name',
            'type' => 'varchar',
        ));

        $this->addColumn('customer_name', array(
            'header' => Mage::helper('customer')->__('Customer Name'),
            'index' => 'name'
        ));
        $this->addColumn('email', array(
            'header' => Mage::helper('customer')->__('Customer Email'),
            'width' => '150',
            'index' => 'email'
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
            'index' => 'wishlist_updated_at',
            'type' => 'datetime',
            'width' => '200px',
        ));

        $this->addColumn('singleAction', array(
            'header' => Mage::helper('sales')->__('Action'),
            'width' => '50px',
            'type' => 'action',
            'getter' => 'getWishlistId',
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

    /**
     * @return $this
     */
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