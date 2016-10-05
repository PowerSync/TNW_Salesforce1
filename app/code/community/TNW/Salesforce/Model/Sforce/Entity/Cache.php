<?php

/**
 * Class TNW_Salesforce_Model_Sforce_Entity_Cache
 *
 * @method TNW_Salesforce_Model_Mysql4_Entity_Cache getResource
 * @method TNW_Salesforce_Model_Mysql4_Entity_Cache_Collection getCollection
 */
class TNW_Salesforce_Model_Sforce_Entity_Cache extends Mage_Core_Model_Abstract
{
    const CACHE_TYPE_ACCOUNT  = 'account';
    const CACHE_TYPE_CAMPAIGN = 'campaign';
    const CACHE_TYPE_USER     = 'user';

    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/entity_cache');
    }

    /**
     * @param $name
     * @param $objectType
     * @param int $page
     * @return array
     * @throws Exception
     */
    public function searchByName($name, $objectType, $page = 1)
    {
        $collection = $this->getCollection()
            ->addFieldToFilter('name', array('like' => "%$name%"))
            ->addFieldToFilter('object_type', array('eq' => $objectType));

        if (!$collection->getSize()) {
            $collection = $this->generateCollectionByType($objectType)
                ->addFieldToFilter('name', array('like' => "%$name%"));
        }

        $collection
            ->setPageSize(TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection::PAGE_SIZE)
            ->setCurPage($page);

        if ($collection instanceof TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract) {
            foreach ($collection as $item) {
                (new self)
                    ->setData(array(
                        'id'          => $item->getId(),
                        'name'        => $item->getData('Name'),
                        'object_type' => $objectType,
                    ))
                    ->save();
            }
        }

        return array(
            'totalRecords' => $collection->getSize(),
            'items' => array_map(function ($item) {
                return array(
                    'id'    => $item->getId(),
                    'text'  => $item->hasData('name') ? $item->getData('name') : $item->getData('Name')
                );
            }, array_values($collection->getItems()))
        );
    }

    /**
     * @param $type
     * @return TNW_Salesforce_Model_Mysql4_Entity_Cache_Collection|TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract
     * @throws Exception
     */
    protected function generateCollectionByType($type)
    {
        switch ($type) {
            case self::CACHE_TYPE_ACCOUNT:
                return Mage::getResourceModel('tnw_salesforce_api_entity/account_collection')
                    ->setFullIdMode(true);

            case self::CACHE_TYPE_CAMPAIGN:
                return Mage::getResourceModel('tnw_salesforce_api_entity/campaign_collection')
                    ->setFullIdMode(true);

            case self::CACHE_TYPE_USER:
                return Mage::getResourceModel('tnw_salesforce_api_entity/user_collection')
                    ->setFullIdMode(true);

            default:
                throw new Exception('Unknown type');
        }
    }

    /**
     * @param $id
     * @param $objectType
     * @return array
     */
    public function toArraySearchById($id, $objectType)
    {
        return $this->getResource()->toArraySearchById($id, $objectType);
    }
}