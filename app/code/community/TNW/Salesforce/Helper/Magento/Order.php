<?php

class TNW_Salesforce_Helper_Magento_Order extends TNW_Salesforce_Helper_Magento_Abstract
{
    /**
     * @param stdClass $object
     * @return mixed
     */
    public function syncFromSalesforce($object = null)
    {
        $this->_prepare();

        $_mMagentoId = null;

        $_sSalesforceId = (property_exists($object, "Id") && $object->Id)
            ? $object->Id : null;

        if (!$_sSalesforceId) {
            Mage::getSingleton('tnw_salesforce/tool_log')->saveError("ERROR upserting order into Magento: ID is missing");
            $this->_addError('Could not upsert Order into Magento, salesforce ID is missing', 'SALESFORCE_ID_IS_MISSING');
            return false;
        }

        $_sMagentoIdKey = TNW_Salesforce_Helper_Config::SALESFORCE_PREFIX_PROFESSIONAL . "Magento_ID__c";
        $_sMagentoId    = (!empty($object->$_sMagentoIdKey))
            ? $object->$_sMagentoIdKey : null;

        $orderTable = Mage::helper('tnw_salesforce')->getTable('sales_flat_order');
        if (!empty($_sMagentoId)) {
            //Test if user exists
            $sql = "SELECT increment_id  FROM `$orderTable` WHERE increment_id = '$_sMagentoId'";
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $_mMagentoId = $row['increment_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Order loaded using Magento ID: " . $_mMagentoId);
            }
        }

        if (is_null($_mMagentoId) && !empty($_sSalesforceId)) {
            // Try to find the user by SF Id
            $sql = "SELECT increment_id FROM `$orderTable` WHERE salesforce_id = '$_sSalesforceId'";
            $row = $this->_write->query($sql)->fetch();
            if ($row) {
                $_mMagentoId = $row['increment_id'];

                Mage::getSingleton('tnw_salesforce/tool_log')
                    ->saveTrace("Order #" . $_mMagentoId . " Loaded by using Salesforce ID: " . $_sSalesforceId);
            }
        }

        return $this->_updateMagento($object, $_mMagentoId, $_sSalesforceId);
    }

    protected function _updateMagento($object, $_mMagentoId, $_sSalesforceId)
    {
        if ($_mMagentoId) {
            /** @var Mage_Sales_Model_Order $creditMemo */
            $order = Mage::getModel('sales/order')
                ->load($_mMagentoId, 'increment_id');
        }
        else {
            $orderCreate   = Mage::getSingleton('adminhtml/sales_order_create');
            /* @var $productHelper Mage_Catalog_Helper_Product */
            $productHelper = Mage::helper('catalog/product');

            $items = array();
            foreach ($object->OrderItems->records as $record) {
                $product = $this->_searchProductByPricebook($record->PricebookEntryId);
                if (is_null($product->getId())) {
                    continue;
                }

                if ($product->getTypeId() != Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
                    continue;
                }

                $items[$product->getId()] = array('qty'=>$record->Quantity);
                $buyRequest = new Varien_Object($items[$product->getId()]);
                $params = array('files_prefix' => 'item_' . $product->getId() . '_');
                $buyRequest = $productHelper->addParamsToBuyRequest($buyRequest, $params);
                if ($buyRequest->hasData()) {
                    $items[$product->getId()] = $buyRequest->toArray();
                }
            }

            if (empty($items)) {
                Mage::throwException('');
            }

            $orderCreate->addProducts($items);
            $orderCreate->importPostData(array(
                'account'           => array(
                    'group_id'  => 1,
                    'email'     => 'b.hugos@example.su',
                ),
                'billing_address'   => array(
                    'save_in_address_book' => 0,
                    'street'     =>     array(''),
                    'city'       =>     '',
                    'country_id' =>     '',
                    'region_id'  =>     '',
                ),
                'shipping_address'  => array(
                    'save_in_address_book' => 0,
                    'street'     =>     array(''),
                    'city'       =>     '',
                    'country_id' =>     '',
                    'region_id'  =>     '',
                ),
                'shipping_method'   => array(),
                'payment_method'    => array(
                    'method'     => ''
                ),
            ));

            $order = $orderCreate->createOrder();
        }

        $order->addData(array(
            'salesforce_id' => $_sSalesforceId
        ));

        return $order;
    }

    protected function _searchProductByPricebook($pricebookEntryId)
    {
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->addAttributeToFilter('salesforce_pricebook_id', array('like'=>"%$pricebookEntryId%"));
        return $collection->getFirstItem();
    }
}