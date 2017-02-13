<?php

class TNW_Salesforce_Model_Mapping_Type_Order_Shipment extends TNW_Salesforce_Model_Mapping_Type_Abstract
{
    const TYPE = 'Shipment';

    /**
     * @param $_entity Mage_Sales_Model_Order_Shipment
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

            case 'sf_status':
                return $this->convertSfStatus($_entity);

            case 'sf_name':
                return $this->convertSfName($_entity);

            case 'track_number':
                return $this->convertTrackNumber($_entity);
        }

        return parent::_prepareValue($_entity);
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $_entity
     * @return string
     */
    public function convertCartAll($_entity)
    {
        ## Put Products into Single field
        $delimiter = '=======================================';
        $lines = array();
        $lines[] = 'Items shipment:';
        $lines[] = $delimiter;
        $lines[] = 'SKU, Qty, Name';
        $lines[] = $delimiter;

        /** @var Mage_Sales_Model_Order_Shipment_Item $item */
        foreach ($_entity->getItemsCollection() as $itemId => $item) {
            if ($item->isDeleted() || $item->getOrderItem()->getParentItem()) {
                continue;
            }

            $lines[] = implode(', ', array(
                $item->getSku(),
                $this->numberFormat($item->getQty()),
                $item->getName(),
            ));
        }

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $_entity
     * @return string
     */
    public function convertNumber($_entity)
    {
        return $_entity->getIncrementId();
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $_entity
     * @return string
     */
    public function convertWebsite($_entity)
    {
        /** @var tnw_salesforce_helper_magento_websites $websiteHelper */
        $websiteHelper = Mage::helper('tnw_salesforce/magento_websites');
        $_website = Mage::app()
            ->getStore($_entity->getStoreId())
            ->getWebsite();

        return $websiteHelper->getWebsiteSfId($_website);
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $_entity
     * @return string
     */
    public function convertSfStatus($_entity)
    {
        //TODO: ??
        return '';
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $_entity
     * @return string
     */
    public function convertSfName($_entity)
    {
        return $_entity->getIncrementId();
    }

    /**
     * @param Mage_Sales_Model_Order_Shipment $_entity
     * @return string
     */
    public function convertTrackNumber($_entity)
    {
        $allTracks = $_entity->getAllTracks();
        $track = reset($allTracks);
        if ($track) {
            return $track->getTrackNumber();
        }

        return '';
    }
}