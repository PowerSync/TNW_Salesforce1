<?php

class TNW_Salesforce_Model_Mapping_Type_Wishlist extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Wishlist';

    /**
     * @param $_entity Mage_Wishlist_Model_Wishlist
     * @return string
     */
    protected function _prepareValue($_entity)
    {
        $attribute = $this->_mapping->getLocalFieldAttributeCode();
        switch ($attribute) {
            case 'cart_all':
                return $this->convertCartAll($_entity);

            case 'number':
                return $this->convertNumber($_entity);

            case 'website':
                return $this->convertWebsite($_entity);

            case 'sf_stage':
                return $this->convertSfStage($_entity);

            case 'sf_name':
                return $this->convertSfName($_entity);

            case 'sf_close_date':
                return $this->convertCloseDate($_entity);

            case 'owner_salesforce_id':
                return $this->convertOwnerSalesforceId($_entity);
        }

        return parent::_prepareValue($_entity);
    }

    /**
     * @param Mage_Wishlist_Model_Wishlist $_entity
     * @return string
     * @throws \Mage_Core_Exception
     * @throws \Mage_Core_Model_Store_Exception
     */
    public function convertCartAll($_entity)
    {
        /** @var TNW_Salesforce_Helper_Data $helper */
        $helper = Mage::helper('tnw_salesforce');

        $baseCurrency = Mage::helper('tnw_salesforce/config_sales')->useBaseCurrency();
        $currency = $baseCurrency ? Mage::app()->getStore()->getBaseCurrencyCode() : Mage::app()->getStore()->getCurrencyCode();

        ## Put Products into Single field
        $delimiter = '=======================================';
        $lines = array();
        $lines[] = 'Items wishlist:';
        $lines[] = $delimiter;
        $lines[] = 'SKU, Qty, Name, Price';
        $lines[] = $delimiter;

        /**
         * @var $item Mage_Wishlist_Model_Item
         */
        $wishistItem = Mage::helper('tnw_salesforce/salesforce_wishlist')->getItems($_entity);
        foreach ($wishistItem as $itemId => $item) {
            $lines[] = implode(', ', array(
                $item->getProduct()->getSku(),
                $helper->numberFormat($item->getData('qty')),
                $item->getProduct()->getName(),
                $currency . $helper->numberFormat($item->getProduct()->getFinalPrice()),
            ));
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param Mage_Wishlist_Model_Wishlist $_entity
     * @return string
     */
    public function convertNumber($_entity)
    {
        return TNW_Salesforce_Helper_Salesforce_Wishlist::SALESFORCE_ENTITY_PREFIX . $_entity->getId();
    }

    /**
     * @param Mage_Wishlist_Model_Wishlist $_entity
     * @return string
     * @throws Exception
     */
    public function convertWebsite($_entity)
    {
        /** @var tnw_salesforce_helper_magento_websites $websiteHelper */
        $websiteHelper = Mage::helper('tnw_salesforce/magento_websites');

        $websiteId = Mage::getSingleton('tnw_salesforce/localstorage')
            ->getWebsiteIdForType('wishlist/wishlist', $_entity->getId());

        return $websiteHelper->getWebsiteSfId($websiteId);
    }

    /**
     * @param Mage_Wishlist_Model_Wishlist $_entity
     * @return string
     */
    public function convertSfStage($_entity)
    {
        if ($stage = Mage::helper('tnw_salesforce/config_wishlist')->stageName()) {
            return $stage;
        }

        return 'Committed';
    }

    /**
     * @param Mage_Wishlist_Model_Wishlist $_entity
     * @return string
     */
    public function convertSfName($_entity)
    {
        return "Wishlist #" . $this->convertNumber($_entity);
    }

    /**
     * @param Mage_Wishlist_Model_Wishlist $_entity
     * @return string
     */
    public function convertCloseDate($_entity)
    {
        switch (Mage::helper('tnw_salesforce/config_wishlist')->closeDate()) {
            case TNW_Salesforce_Model_Config_Wishlist_CloseDate::ONE_WEEK:
                $intervalSpec = 'P1W';
                break;
            case TNW_Salesforce_Model_Config_Wishlist_CloseDate::TWO_WEEK:
                $intervalSpec = 'P2W';
                break;
            case TNW_Salesforce_Model_Config_Wishlist_CloseDate::THREE_WEEK:
                $intervalSpec = 'P3W';
                break;
            case TNW_Salesforce_Model_Config_Wishlist_CloseDate::ONE_MONTH:
                $intervalSpec = 'P1M';
                break;
            case TNW_Salesforce_Model_Config_Wishlist_CloseDate::THREE_MONTH:
                $intervalSpec = 'P3M';
                break;
            case TNW_Salesforce_Model_Config_Wishlist_CloseDate::SIX_MONTH:
                $intervalSpec = 'P6M';
                break;
            default:
                return null;
        }

        /**
         * reduce the time to compensate Time zone offset
         */
        $timeOffsetInterval = new DateInterval($intervalSpec);

        $this->_mapping->setLocalFieldAttributeCode('updated_at');
        $closeDate = $this->_prepareDateTime($_entity->getUpdatedAt())
            ->add($timeOffsetInterval)
            ->format('c');

        return $closeDate;
    }

    /**
     * @param Mage_Sales_Model_Order $_entity
     * @return string
     */
    public function convertOwnerSalesforceId($_entity)
    {
        return Mage::helper('tnw_salesforce')->getDefaultOwner();
    }
}