<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Customersync_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    protected $_allowedCustomerGroups = array();

    public function __construct()
    {
        parent::__construct();
        $this->setId('customerGrid');
        $this->setUseAjax(true);
        $this->setDefaultSort('sf_insync');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        if (!Mage::helper('tnw_salesforce')->getSyncAllGroups()) {
            $this->_allowedCustomerGroups = Mage::helper('tnw_salesforce')->getAllowedCustomerGroups();
        }

        $collection = Mage::getResourceModel('customer/customer_collection')
            ->addNameToSelect()
            ->addAttributeToSelect('email')
            ->addAttributeToSelect('created_at')
            ->addAttributeToSelect('sf_insync')
            ->addAttributeToSelect('salesforce_id')
            ->addAttributeToSelect('salesforce_account_id')
            ->addAttributeToSelect('salesforce_lead_id')
            ->addAttributeToSelect('group_id')
            ->joinAttribute('billing_postcode', 'customer_address/postcode', 'default_billing', null, 'left')
            ->joinAttribute('billing_city', 'customer_address/city', 'default_billing', null, 'left')
            ->joinAttribute('billing_telephone', 'customer_address/telephone', 'default_billing', null, 'left')
            ->joinAttribute('billing_region', 'customer_address/region', 'default_billing', null, 'left')
            ->joinAttribute('billing_country_id', 'customer_address/country_id', 'default_billing', null, 'left');
        if (!empty($this->_allowedCustomerGroups)) {
            $collection->addFieldToFilter('group_id', array('in' => $this->_allowedCustomerGroups));
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
            'renderer' => 'TNW_Salesforce_Block_Adminhtml_Renderer_Entity_Status'
        ));

        $this->addColumn('entity_id', array(
            'header' => Mage::helper('customer')->__('ID'),
            'width' => '50px',
            'index' => 'entity_id',
            'type' => 'number',
        ));

        $this->addColumn('name', array(
            'header' => Mage::helper('customer')->__('Name'),
            'index' => 'name'
        ));
        $this->addColumn('email', array(
            'header' => Mage::helper('customer')->__('Email'),
            'width' => '150',
            'index' => 'email'
        ));

        $this->addColumn('billing_postcode', array(
            'header' => Mage::helper('customer')->__('ZIP'),
            'width' => '90',
            'index' => 'billing_postcode',
        ));

        $this->addColumn('billing_country_id', array(
            'header' => Mage::helper('customer')->__('Country'),
            'width' => '100',
            'type' => 'country',
            'index' => 'billing_country_id',
        ));

        $this->addColumn('billing_region', array(
            'header' => Mage::helper('customer')->__('State/Province'),
            'width' => '100',
            'index' => 'billing_region',
        ));

        $this->addColumn('salesforce_id', array(
            'header' => Mage::helper('sales')->__('Contact ID'),
            'index' => 'salesforce_id',
            'type' => 'varchar',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Id(),
        ));

        $this->addColumn('salesforce_account_id', array(
            'header' => Mage::helper('sales')->__('Account ID'),
            'index' => 'salesforce_account_id',
            'type' => 'varchar',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Account_Id(),
        ));

        $this->addColumn('salesforce_lead_id', array(
            'header' => Mage::helper('sales')->__('Lead ID'),
            'index' => 'salesforce_lead_id',
            'type' => 'varchar',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Lead_Id(),
        ));

        if (!Mage::app()->isSingleStoreMode()) {
            $this->addColumn('website_id', array(
                'header' => Mage::helper('customer')->__('Website'),
                'align' => 'center',
                'width' => '80px',
                'type' => 'options',
                'options' => Mage::getSingleton('adminhtml/system_store')->getWebsiteOptionHash(true),
                'index' => 'website_id',
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
                        'field' => 'customer_id'
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
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', array('_current' => true));
    }
}