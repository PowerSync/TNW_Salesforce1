<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Renderer_Link_Queue extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * @param Varien_Object $row
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $objectId     = $row->getData('object_id');
        switch ($row->getData('mage_object_type')) {
            case 'sales/order':
                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/sales_order/view', array('order_id'=>$objectId)), $objectId);

            case 'sales/quote':
                $quote = Mage::getModel('sales/quote')
                    ->setSharedStoreIds(array_keys(Mage::app()->getStores()))
                    ->load($objectId);

                if (!$quote->getData('customer_id')) {
                    return $objectId;
                }

                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/customer/edit', array('id'=>$quote->getData('customer_id'), 'active_tab' => 'cart')), $objectId);

            case 'customer/customer':
                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/customer/edit', array('id'=>$objectId)), $objectId);

            case 'catalog/product':
                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/catalog_product/edit', array('id'=>$objectId)), $objectId);

            case 'core/website':
                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/system_store/editWebsite', array('website_id'=>$objectId)), $objectId);

            case 'sales/order_invoice':
                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/sales_invoice/view', array('invoice_id'=>$objectId)), $objectId);

            case 'sales/order_shipment':
                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/sales_shipment/view', array('shipment_id'=>$objectId)), $objectId);

            case 'sales/order_creditmemo':
                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/sales_creditmemo/view', array('creditmemo_id'=>$objectId)), $objectId);

            case 'catalogrule/rule':
                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/promo_catalog/edit', array('id'=>$objectId)), $objectId);

            case 'salesrule/rule':
                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/promo_quote/edit', array('id'=>$objectId)), $objectId);

            case 'wishlist/wishlist':
                $quote = Mage::getModel('wishlist/wishlist')
                    ->load($objectId);

                if (!$quote->getData('customer_id')) {
                    return $objectId;
                }

                return sprintf('<a href="%s">%s</a>', $this->getUrl('*/customer/edit', array('id'=>$quote->getData('customer_id'), 'active_tab' => 'wishlist')), $objectId);
        }

        return $objectId;
    }
}