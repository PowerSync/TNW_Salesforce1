<?php

/**
 * Created by PhpStorm.
 * User: eermolaev
 * Date: 17.02.17
 * Time: 20:23
 */
class TNW_Salesforce_Model_Eav_Entity_Attribute_Set_Observer
{

    /**
     * key - is attribute code, value - flag (true : need to add our attribute to set,false : attribute already is here )
     * @var array
     */
    protected $_ourAttributes = array(
        'salesforce_campaign_id' => true,
        'salesforce_disable_sync' => true,
        'salesforce_id' => true,
        'salesforce_pricebook_id' => true,
        'sf_insync' => true
    );

    /**
     * key - is attribute code, value - flag (true : need to add our attribute to set,false : attribute already is here )
     * @var array
     */
    protected $_ourAttributesModel = array();

    /**
     * @var Mage_Eav_Model_Entity_Attribute_Group
     */
    protected $_temporaryGroup = null;

    /**
     * TNW_Salesforce_Model_Eav_Entity_Attribute_Set_Observer constructor.
     */
    public function __construct()
    {
        /**
         * load our attributes data
         */
        /** @var Mage_Catalog_Model_Resource_Product_Attribute_Collection $collection */
        $attributes = Mage::getResourceModel('catalog/product_attribute_collection');
        $attributes->addFieldToFilter('main_table.attribute_code', array_keys($this->_ourAttributes));

        $this->_ourAttributesModel = $attributes;
    }


    /**
     *
     */
    public function getOurAttributes()
    {
        return $this->_ourAttributes;
    }


    /**
     * @param array $ourAttributes
     * @return $this
     */
    public function setOurAttributes($ourAttributes)
    {
        $this->_ourAttributes = $ourAttributes;
        return $this;
    }

    /**
     * @param $attributes
     * @param $ourAttributes
     * @return mixed
     */
    public function removeExistingAttributes($attributes, $ourAttributes)
    {

        if (!empty($attributes)) {

            /** @var Mage_Eav_Model_Entity_Attribute $attribute */
            foreach ($attributes as $attribute) {
                if ($item = $this->_ourAttributesModel->getItemById($attribute->getId())) {
                    unset($ourAttributes[$item->getAttributeCode()]);
                }
            }
        }

        return $ourAttributes;
    }

    /**
     * @param array $ourAttributes
     * @param Mage_Eav_Model_Entity_Attribute_Set $attributeSet
     * @return array
     */
    protected function removeAlreadyExistingAttributes($ourAttributes, $attributeSet)
    {

        $groups = $attributeSet->getGroups();
        /**
         * skip initial save without any groups
         */
        if (empty($groups)) {
            return array();
        }

        /** @var Mage_Eav_Model_Entity_Attribute_Group $group */
        foreach ($groups as $group) {
            $ourAttributes = $this->removeExistingAttributes($group->getAttributes(), $ourAttributes);
        }

        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Collection $filteredAttributes */
        $attributes = Mage::getResourceModel('eav/entity_attribute_collection');
        $attributes->setAttributeSetFilter($attributeSet->getId());

        $attributes->addFieldToFilter('main_table.entity_type_id', $attributeSet->getEntityTypeId());

        if (!empty($ourAttributes)) {
            $attributes->addFieldToFilter('main_table.attribute_code', array_keys($ourAttributes));
        }

        $ourAttributes = $this->removeExistingAttributes($attributes, $ourAttributes);

        return $ourAttributes;
    }

    /**
     * @param array $ourAttributes
     * @param Mage_Eav_Model_Entity_Attribute_Set $attributeSet
     * @return  Mage_Eav_Model_Entity_Attribute_Group
     */
    protected function createTemporraryGroup($ourAttributes, $attributeSet)
    {

        /** @var Mage_Eav_Model_Entity_Attribute_Group $newGroup */
        $newGroup = Mage::getModel('eav/entity_attribute_group');

        $newGroup->setId(null)
            ->setAttributeSetId($attributeSet->getId());

        $newAttributes = array();
        foreach ($ourAttributes as $ourAttribute => $flag) {
            $attribute = Mage::getModel('eav/entity_attribute')
                ->loadByCode(Mage::getModel('catalog/product')->getResource()->getTypeId(), $ourAttribute);
            if ($attribute->getId()) {
                $newAttribute = Mage::getModel('eav/entity_attribute')
                    ->setId($attribute->getId())
                    ->setAttributeSetId($attributeSet->getId())
                    ->setEntityTypeId($attributeSet->getEntityTypeId())
                    ->setSortOrder($attribute->getSortOrder());


                $newAttributes[] = $newAttribute;
            }
        }

        $newGroup->setAttributes($newAttributes);

        return $newGroup;
    }

    /**
     * copy our attributes to new Set
     *
     * @param $observer
     * @return $this
     */
    public function saveBefore($observer)
    {
        /** @var Mage_Eav_Model_Entity_Attribute_Set $attributeSet */
        $attributeSet = $observer->getObject();

        if ($attributeSet->getEntityTypeId() != Mage::getModel('catalog/product')->getResource()->getTypeId()) {
            return $this;
        }

        $ourAttributes = $this->getOurAttributes();

        $ourAttributes = $this->removeAlreadyExistingAttributes($ourAttributes, $attributeSet);

        /**
         * remove attributes added already
         */
        $ourAttributes = array_filter($ourAttributes);

        if (empty($ourAttributes)) {
            return $this;
        }

        $newGroup = $this->createTemporraryGroup($ourAttributes, $attributeSet);

        $groups = $attributeSet->getGroups();
        $groups[] = $newGroup;
        $this->_temporaryGroup = $newGroup;

        $attributeSet->setGroups($groups);

    }

    /**
     * remove temporrary group, we'll use our custom tab instead
     * @param $observer
     */
    public function saveAfter($observer)
    {
        if ($this->_temporaryGroup !== null) {
            $attributeSet = $observer->getObject();

            /**
             * need $setup to use specific sql options defined in startSetup method
             */
            $setup = Mage::getModel('eav/entity_setup', 'core_setup');
            $setup->startSetup();

            $setup->removeAttributeGroup('catalog_product', $attributeSet->getId(), $this->_temporaryGroup->getId());

            $setup->endSetup();
        }
    }
}