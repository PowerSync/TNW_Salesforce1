<?php

class TNW_Salesforce_Block_Adminhtml_Customer_Edit_Tab_View_Opportunities extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        parent::__construct();
        $this->setId('customer_opportunities_grid');
        $this->setDefaultSort('CreatedBy', 'desc');
        $this->setUseAjax(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('tnw_salesforce_api_entity/opportunity_collection');

        switch (Mage::helper('tnw_salesforce/config_customer')->getOpportunityFilterType())
        {
            case TNW_Salesforce_Model_Config_Opportunity_Filter::FILTER_CUSTOMER:
                $select = $collection->getConnection()->select()
                    ->from('OpportunityContactRole', array('OpportunityId'))
                    ->where('ContactId = ?', Mage::registry('current_customer')->getData('salesforce_id'));

                $collection->addFieldToFilter('Id', array('in' => new Zend_Db_Expr($select->assemble())));
                break;

            case TNW_Salesforce_Model_Config_Opportunity_Filter::FILTER_ACCOUNT;
            default:
                $collection->addFieldToFilter('AccountId', Mage::registry('current_customer')->getData('salesforce_account_id'));
                break;
        }

        $column = array('Id', 'Name', 'Owner.Name', 'Amount', 'StageName', 'CreatedDate', 'LastModifiedDate', 'CloseDate');
        if (Mage::helper('tnw_salesforce')->isMultiCurrency()) {
            $column[] = 'CurrencyIsoCode';
        }

        $collection->getSelect()
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns($column);

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _beforeToHtml()
    {
        if (Mage::helper('tnw_salesforce')->getType() != "PRO") {
            return $this;
        }

        return parent::_beforeToHtml();
    }

    protected function _afterLoadCollection()
    {
        $this->getCollection()->walk(function(Varien_Object $item) {
            $owner = $item->getData('Owner');
            if (!is_object($owner)) {
                return;
            }

            if (!Mage::helper('tnw_salesforce')->isMultiCurrency()) {
                $item->setData('CurrencyIsoCode', Mage::app()->getStore()->getBaseCurrencyCode());
            }

            $item->setData('OwnerName', $owner->Name);

            $timezone = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
            $dateTime = new \DateTime(date('Y-m-d H:i:s', strtotime($item->getData('CloseDate'))), new \DateTimeZone($timezone));
            $item->setData('CloseDate', $dateTime->format('c'));

            $timezone = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
            $dateTime = new \DateTime(date('Y-m-d H:i:s', strtotime($item->getData('CreatedDate'))), new \DateTimeZone($timezone));
            $item->setData('CreatedDate', $dateTime->format('c'));

            $timezone = Mage::app()->getStore()->getConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_TIMEZONE);
            $dateTime = new \DateTime(date('Y-m-d H:i:s', strtotime($item->getData('LastModifiedDate'))), new \DateTimeZone($timezone));
            $item->setData('LastModifiedDate', $dateTime->format('c'));
        });

        return $this;
    }

    protected function _prepareColumns()
    {

        $this->addColumn('Id', array(
            'header'    => Mage::helper('customer')->__('Opportunity ID'),
            'index'     => 'Id',
            'width'     => '140px',
            'renderer'  => new TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Id(),
        ));

        $this->addColumn('Name', array(
            'header'    => Mage::helper('customer')->__('Opportunity Name'),
            'index'     => 'Name',
            'type'      => 'varchar',
        ));

        $this->addColumn('OwnerName', array(
            'header'        => Mage::helper('customer')->__('Opportunity Owner'),
            'index'         => 'OwnerName',
            'filter_index'  => 'Owner.Name',
            'type'          => 'varchar',
        ));

        $this->addColumn('StageName', array(
            'header'        => Mage::helper('customer')->__('Stage'),
            'index'         => 'StageName',
            'type'          => 'varchar',
        ));

        $this->addColumn('CloseDate', array(
            'header'        => Mage::helper('customer')->__('Close Date'),
            'index'         => 'CloseDate',
            'type'          => 'date',
        ));

        $this->addColumn('CreatedDate', array(
            'header'        => Mage::helper('customer')->__('Created Date'),
            'index'         => 'CreatedDate',
            'type'          => 'datetime',
            'filter_time'   => true
        ));

        $this->addColumn('LastModifiedDate', array(
            'header'        => Mage::helper('customer')->__('Update Date'),
            'index'         => 'LastModifiedDate',
            'type'          => 'datetime',
            'filter_time'   => true,
        ));

        $this->addColumn('Amount', array(
            'header'    => Mage::helper('customer')->__('Amount'),
            'index'     => 'Amount',
            'type'      => 'currency',
            'currency'  => 'CurrencyIsoCode',
        ));

        return parent::_prepareColumns();
    }

    public function getRowUrl($row)
    {
        return '';
    }

    public function getHeadersVisibility()
    {
        return ($this->getCollection()->getSize() > 0);
    }

}