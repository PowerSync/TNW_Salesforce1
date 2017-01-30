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

    const IMPORT_PAGE_SIZE    = 300;

    /**
     * @var array
     */
    protected $cacheTypes = array(
        self::CACHE_TYPE_ACCOUNT,
        self::CACHE_TYPE_CAMPAIGN,
        self::CACHE_TYPE_USER,
    );

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
     */
    public function searchByName($name, $objectType, $page = 1)
    {
        $websiteId = Mage::app()->getWebsite()->getId();

        $collection = $this->getCollection()
            ->addFieldToFilter('name', array('like' => "%$name%"))
            ->addFieldToFilter('object_type', array('eq' => $objectType))
            ->addFieldToFilter('website_id', array('eq' => $websiteId));

        if (!$collection->getSize()) {
            $collection = $this->generateCollectionByType($objectType)
                ->addFieldToFilter('name', array('like' => "%$name%"));
        }

        $collection
            ->setPageSize(TNW_Salesforce_Model_Api_Entity_Resource_Account_Collection::PAGE_SIZE)
            ->setCurPage($page);

        if ($collection instanceof TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract) {
            foreach ($collection as $item) {
                $cache = new self;
                $cache
                    ->setData(array(
                        'id'          => $item->getId(),
                        'name'        => $item->getData('Name'),
                        'object_type' => $objectType,
                        'website_id'  => $websiteId
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
     * @return TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract
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
        $websiteId = Mage::app()->getWebsite()->getId();
        $result = $this->getResource()->toArraySearchById($id, $objectType, $websiteId);

        if (empty($result)) {
            $collection = $this->generateCollectionByType($objectType)
                ->addFieldToFilter('Id', array('eq' => $id));

            $result = array();
            foreach ($collection as $item) {
                $result[] = array(
                    'value' => $item['Id'],
                    'label' => $item['Name']
                );

                $cache = new self;
                $cache
                    ->setData(array(
                        'id'          => $item['Id'],
                        'name'        => $item['Name'],
                        'object_type' => $objectType,
                        'website_id'  => $websiteId
                    ))
                    ->save();
            }
        }

        return $result;
    }

    /**
     *
     */
    public function importFromSalesforce()
    {
        $websiteId = Mage::app()->getWebsite()->getId();

        foreach ($this->cacheTypes as $cacheType) {
            /** @var TNW_Salesforce_Model_Api_Entity_Resource_Collection_Abstract $collection */
            $collection = $this->generateCollectionByType($cacheType)
                ->setPageSize(self::IMPORT_PAGE_SIZE);

            $collection->getSelect()->order('Id ASC');
            $lastPageNumber = $collection->getLastPageNumber();
            $this->getResource()->clearType($cacheType, $websiteId);

            for($i = 1; $i <= $lastPageNumber; $i++) {
                $collection->clear()->setCurPage($i);
                $data = array();
                foreach ($collection as $item) {
                    $data[] = array(
                        'id'            => $item->getId(),
                        'name'          => $item->getData('Name'),
                        'object_type'   => $cacheType,
                        'website_id'    => $websiteId
                    );
                }

                $this->getResource()->massImport($data);
            }
        }
    }

    /**
     * @return $this
     */
    public function clearAll()
    {
        $this->getResource()->clearAll();
        return $this;
    }
}