<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @method Mage_Customer_Model_Resource_Customer_Collection getCollection()
 */
class TNW_Salesforce_Block_Adminhtml_Synchronize_Wishlist_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    /**
     * TNW_Salesforce_Block_Adminhtml_Synchronize_Wishlist_Grid constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->setId('wishlistGrid');
        $this->setUseAjax(true);
        //$this->setDefaultSort('sf_insync');
        //$this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _prepareCollection()
    {
        /** @var Mage_Customer_Model_Resource_Customer_Collection $collection */
        $collection = Mage::getResourceModel('customer/customer_collection');
        $collection
            ->addNameToSelect()
            ->addAttributeToSelect('email')
            ->joinTable(array('wishlist'=>'wishlist/wishlist'), 'customer_id=entity_id', array(
                'wishlist_id',
                'wishlist_name' => 'name',
                'wishlist_updated_at' => 'updated_at',
                'wishlist_sf_insync' => 'sf_insync',
                'wishlist_salesforce_id' => 'salesforce_id',
            ))
        ;

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    /**
     * @return $this
     */
    protected function _prepareColumns()
    {
        $this->addColumn('wishlist_sf_insync', array(
            'header' => Mage::helper('sales')->__('Status'),
            'width' => '40',
            'type' => 'options',
            'options' => array(
                0 => 'No',
                1 => 'Yes',
            ),
            'index' => 'wishlist_sf_insync',
            'renderer' => 'tnw_salesforce/adminhtml_renderer_entity_status'
        ));

        $this->addColumn('entity_id', array(
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

        $this->addColumn('customer_email', array(
            'header' => Mage::helper('customer')->__('Customer Email'),
            'width' => '150',
            'index' => 'email'
        ));

        $this->addColumn('salesforce_id', array(
            'header' => Mage::helper('sales')->__('Salesforce ID'),
            'index' => 'wishlist_salesforce_id',
            'type' => 'varchar',
            'width' => '140',
            'renderer' => 'tnw_salesforce/adminhtml_renderer_link_salesforce_id',
        ));

        $this->addColumn('updated_at', array(
            'header' => Mage::helper('sales')->__('Updated At'),
            'index' => 'wishlist_updated_at',
            'type' => 'datetime',
            'width' => '200',
        ));

        $this->addColumn('singleAction', array(
            'header' => Mage::helper('sales')->__('Action'),
            'width' => '50',
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
     * Sets sorting order by some column
     *
     * @param Mage_Adminhtml_Block_Widget_Grid_Column $column
     * @return Mage_Adminhtml_Block_Widget_Grid
     */
    protected function _setCollectionOrder($column)
    {
        switch ($column->getId()) {
            case 'entity_id':
                $this->getCollection()->getSelect()
                    ->order('wishlist.wishlist_id '.strtoupper($column->getDir()));
                break;

            case 'name':
                $this->getCollection()->getSelect()
                    ->order('wishlist.name '.strtoupper($column->getDir()));
                break;

            case 'salesforce_id':
                $this->getCollection()->getSelect()
                    ->order('wishlist.salesforce_id '.strtoupper($column->getDir()));
                break;
        }

        return parent::_setCollectionOrder($column);
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