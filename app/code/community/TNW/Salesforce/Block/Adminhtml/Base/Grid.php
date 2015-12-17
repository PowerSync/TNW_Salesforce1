<?php

/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 *
 * Class TNW_Salesforce_Block_Adminhtml_Base_Grid
 */
class TNW_Salesforce_Block_Adminhtml_Base_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    /**
     * name of  Salesforce object in case sensitive
     * @var string
     */
    protected $_sfEntity = '';

    /**
     * name of Local object in case sensitive
     * @var string
     */
    protected $_localEntity = '';

    /**
     * @param bool|false $uc should we make first letter in upper case?
     * @return string
     */
    public function getSfEntity($uc = false)
    {
        $sfEntity = $this->_sfEntity;
        if (!$uc) {
            $sfEntity = strtolower($sfEntity);
        }
        return $sfEntity;
    }

    /**
     * @param bool|false $uc should we make first letter in upper case?
     * @return string
     */
    public function getLocalEntity($uc = false)
    {
        $localEntity = $this->_localEntity;
        if (empty($localEntity)) {
            return $this->getSfEntity($uc);
        }

        if (!$uc) {
            $localEntity = strtolower($localEntity);
        }
        return $localEntity;
    }

    /**
     * @param string $sfEntity
     * @return $this
     */
    public function setSfEntity($sfEntity)
    {
        $this->_sfEntity = $sfEntity;
        return $this;
    }

    /**
     * Internal constructor
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setId($this->getSfEntity() . 'Grid');
        $this->setDefaultSort('mapping_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('tnw_salesforce/mapping')->getCollection()
            ->addObjectToFilter($this->getLocalEntity(true));
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('mapping_id', array(
            'header' => Mage::helper('tnw_salesforce')->__('Mapping ID'),
            'width' => '1',
            'index' => 'mapping_id',
        ));

        $this->addColumn('local_field', array(
            'header' => Mage::helper('tnw_salesforce')->__('Magento Field'),
            'index' => 'local_field',
            'width' => '300',
            'renderer' => new TNW_Salesforce_Block_Adminhtml_Renderer_Mapping_Magento(),
        ));

        $this->addColumn('sf_field', array(
            'header' => Mage::helper('tnw_salesforce')->__('Salesforce Field API Name'),
            'width' => '250',
            'index' => 'sf_field',
        ));

        $this->addColumn('default_value', array(
            'header' => Mage::helper('tnw_salesforce')->__('Default Value'),
            'index' => 'default_value',
        ));

        $this->addColumn('active', array(
            'header' => Mage::helper('sales')->__('Active'),
            'width' => '40px',
            'type' => 'options',
            'options' => Mage::getModel('adminhtml/system_config_source_yesno')->toArray(),
            'index' => 'active',
        ));


        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('mapping_id');
        $this->getMassactionBlock()->setFormFieldName('ids');

        $this->getMassactionBlock()->addItem('delete', array(
            'label' => Mage::helper('tnw_salesforce')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('tnw_salesforce')->__('Are you sure?')
        ));

        return $this;
    }

    /**
     * Row click url
     *
     * @return string
     */
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', array('mapping_id' => $row->getId()));
    }
}
