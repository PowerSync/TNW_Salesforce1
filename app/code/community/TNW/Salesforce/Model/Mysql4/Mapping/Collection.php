<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * Class TNW_Salesforce_Model_Mysql4_Mapping_Collection
 *
 * @method TNW_Salesforce_Model_Mapping getItemById($idValue)
 * @method TNW_Salesforce_Model_Mysql4_Mapping_Collection addFieldToFilter($field, $condition = null)
 */
class TNW_Salesforce_Model_Mysql4_Mapping_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    /**
     *
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/mapping');
    }

    /**
     * @param $so
     * @return $this
     */
    public function addObjectToFilter($so)
    {
        return $this->addFieldToFilter('sf_object', $so);
    }

    /**
     * @param $f
     * @return $this
     */
    public function addLocalFieldToFilter($f)
    {
        return $this->addFieldToFilter('local_field', $f);
    }

    /**
     * @param $update bool
     * @return $this
     */
    public function addFilterTypeMS($update)
    {
        $types = array(
            TNW_Salesforce_Model_Mapping::SET_TYPE_UPSERT,
            $update ? TNW_Salesforce_Model_Mapping::SET_TYPE_UPDATE : TNW_Salesforce_Model_Mapping::SET_TYPE_INSERT
        );

        return $this->addFieldToFilter('magento_sf_enable', 1)
            ->addFieldToFilter('magento_sf_type', array('in' => $types));
    }

    /**
     * @param $update bool
     * @return $this
     */
    public function addFilterTypeSM($update)
    {
        $types = array(
            TNW_Salesforce_Model_Mapping::SET_TYPE_UPSERT,
            $update ? TNW_Salesforce_Model_Mapping::SET_TYPE_UPDATE : TNW_Salesforce_Model_Mapping::SET_TYPE_INSERT
        );

        return $this->addFieldToFilter('sf_magento_enable', 1)
            ->addFieldToFilter('sf_magento_type', array('in' => $types));
    }

    /**
     * @return Varien_Data_Collection_Db
     */
    public function firstSystem()
    {
        return $this->setOrder('is_system', self::SORT_ORDER_DESC);
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();

        /** @var TNW_Salesforce_Model_Mapping $item */
        foreach ($this as $item) {
            $item->afterLoad();
        }

        return $this;
    }

    public function getAllValues(array $objectMappings = array())
    {
        $values = array();
        foreach ($this as $item) {
            $values[$item->getSfField()] = $item->getValue($objectMappings);
        }

        return $values;
    }
}
