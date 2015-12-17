<?php

/**
 * Author: Evgeniy Ermolaev
 * Email: eermolaev@yandex.ru
 * Date: 09.03.15
 * Time: 22:16
 */
abstract class TNW_Salesforce_Model_Sync_Mapping_Abstract_Base
{
    /**
     * @comment Contains Local object name for mapping
     * @var null
     */
    protected $_type = null;

    /**
     * @comment Mapping collection container
     * @var null|TNW_Salesforce_Model_Mysql4_Mapping_Collection
     */
    protected $_mappingCollection = null;

    /**
     * @comment list of the allowed mapping types
     * @var array
     */
    protected $_allowedMappingTypes = array();

    /**
     * @var string
     */
    protected $_cachePrefix = '';

    /**
     * @var string
     */
    protected $_cacheIdField = 'id';

    /**
     * @comment contains instance of the synchronization object for access to some parameters
     * @var null|TNW_Salesforce_Helper_Salesforce_Product|TNW_Salesforce_Helper_Salesforce_Customer|TNW_Salesforce_Helper_Salesforce_Order|TNW_Salesforce_Helper_Salesforce_Opportunity
     */
    protected $_sync = null;

    /**
     * @var array
     */
    protected $_cache = array();

    /**
     * @comment list of the loaded customer groups
     * @var array
     */
    protected $_customerGroups = array();

    /**
     * @comment use this flag to stop mapping after custom mapping realization
     * @var bool
     */
    protected $_break = false;

    /**
     * @comment Apply mapping rules
     */
    abstract protected function _processMapping();

    /**
     * @comment run mapping process
     * @param $entity
     * @param null $additionalObject
     * @throws Exception
     */
    public function processMapping($entity, $additionalObject = null)
    {

        if (is_null($this->_sync)) {
            throw new Exception('Sync object is null! Use "setSync" method to define object.');
        }

        $eventType = strtolower(str_replace(' ', '_', $this->_type));
        Mage::dispatchEvent(
            sprintf('tnw_salesforce_sync_mapping_%s_before', $eventType),
            array(
                'mapping' => $this,
                'entity' => $entity,
                'additionalObject' => $additionalObject
            )
        );

        $this->_processMapping($entity, $additionalObject);

        Mage::dispatchEvent(
            sprintf('tnw_salesforce_sync_mapping_%s_after', $eventType),
            array(
                'mapping' => $this,
                'entity' => $entity,
                'additionalObject' => $additionalObject
            )
        );
    }

    /**
     * @return TNW_Salesforce_Model_Mysql4_Mapping_Collection|null
     */
    public function getMappingCollection()
    {
        if (empty($this->_mappingCollection)) {
            $this->_mappingCollection = Mage::getModel('tnw_salesforce/mapping')
                ->getCollection()
                ->addObjectToFilter($this->_type)
                ->addFieldToFilter('active', 1)
            ;
        }

        return $this->_mappingCollection;
    }

    /**
     * @param $mappingCollection
     * @return $this
     */
    public function setMappingCollection($mappingCollection)
    {
        $this->_mappingCollection = $mappingCollection;

        return $this;
    }

    /**
     * @return null
     */
    public function getType()
    {
        return $this->_type;
    }

    /**
     * @param null $type
     * @return $this
     */
    public function setType($type)
    {
        $this->_type = $type;

        return $this;
    }

    /**
     * @return array
     */
    public function getAllowedMappingTypes()
    {
        return $this->_allowedMappingTypes;
    }

    /**
     * @param $allowedMappingTypes
     * @return $this
     */
    public function setAllowedMappingTypes($allowedMappingTypes)
    {
        $this->_allowedMappingTypes = $allowedMappingTypes;

        return $this;
    }

    /**
     * @param $mappingType
     * @return $this
     */
    public function deleteAllowedMappingType($mappingType)
    {
        unset($this->_allowedMappingTypes[$mappingType]);

        return $this;
    }

    /**
     * @param $mappingType
     * @return bool
     */
    protected function _mappingTypeAllowed($mappingType)
    {
        return in_array($mappingType, $this->_allowedMappingTypes);
    }

    /**
     * @return string
     */
    public function getCachePrefix()
    {
        return $this->_cachePrefix;
    }

    /**
     * @param string $cachePrefix
     * @return $this
     */
    public function setCachePrefix($cachePrefix)
    {
        $this->_cachePrefix = $cachePrefix;

        return $this;
    }

    /**
     * @return string
     */
    public function getCacheIdField()
    {
        return $this->_cacheIdField;
    }

    /**
     * @param string $cacheIdField
     * @return $this
     */
    public function setCacheIdField($cacheIdField)
    {
        $this->_cacheIdField = $cacheIdField;

        return $this;
    }

    /**
     * @return null|TNW_Salesforce_Helper_Salesforce_Product|TNW_Salesforce_Helper_Salesforce_Customer|TNW_Salesforce_Helper_Salesforce_Order|TNW_Salesforce_Helper_Salesforce_Opportunity
     */
    public function getSync()
    {
        return $this->_sync;
    }

    /**
     * @param null|TNW_Salesforce_Helper_Salesforce_Product|TNW_Salesforce_Helper_Salesforce_Customer|TNW_Salesforce_Helper_Salesforce_Abstract_Order $sync
     * @return $this
     */
    public function setSync($sync)
    {
        $this->_sync = $sync;
        /**
         * @comment Passing by Reference
         */
        $this->_cache = &$sync->_cache;

        return $this;
    }

    /**
     * @return stdClass
     */
    public function getObj(){

        return $this->getSync()->getObj();
    }

    /**
     * @return bool
     */
    public function isFromCLI()
    {
        return $this->getSync()->isFromCLI();
    }


    /**
     * @return null|string
     */
    public function getMagentoId()
    {

        return $this->getSync()->getMagentoId();
    }

    /**
     * @param null $key
     * @return array|string
     */
    public function getWebsiteSfIds($key = null)
    {
        return $this->getSync()->getWebsiteSfIds($key);
    }

    /**
     * @param null $_sfUserId
     * @return bool
     */
    protected function _isUserActive($_sfUserId = null )
    {
        return $this->getSync()->isUserActive($_sfUserId);
    }

    /**
     * @comment use this method to define additional action before mapping
     * @return $this
     */
    protected function _fieldMappingBefore($entity, $mappingType, $attributeCode, $value)
    {
        return $value;
    }

    /**
     * @comment use this method to define additional action after mapping
     * @return $this
     */
    protected function _fieldMappingAfter($entity, $mappingType, $attributeCode, $value)
    {
        return $value;
    }

    /**
     * @return boolean
     */
    public function isBreak()
    {
        return $this->_break;
    }

    /**
     * @return boolean
     */
    public function getBreak()
    {
        return $this->isBreak();
    }

    /**
     * @param boolean $break
     */
    public function setBreak($break)
    {
        $this->_break = $break;
    }

    /**
     * @comment based on config returns price in base currency or in currency selected by the customer
     * @param string $entity
     * @param string $priceField should be in camelcase
     */
    protected function _getEntityPrice($entity, $priceField)
    {
        return $this->getSync()->getEntityPrice($entity, $priceField);
    }

    /**
     * @param $value
     * @return string
     */
    protected function _numberFormat($value)
    {
        return $this->getSync()->numberFormat($value);
    }

    /**
     * @return string
     */
    protected function _getCurrencyCode($_entity)
    {
        return $this->getSync()->getCurrencyCode($_entity);
    }

}