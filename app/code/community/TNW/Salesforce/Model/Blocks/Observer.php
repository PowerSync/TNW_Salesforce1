<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Blocks_Observer
{
    /**
     * Update adminhtml/sales_order_view_info html if module enabled
     *
     * @param Varien_Event_Observer $observer
     */
    public function updateBlocks(Varien_Event_Observer $observer)
    {
        $block = $observer->getEvent()->getBlock();
        /** @var Mage_Core_Block_Abstract $block */
        /*
        if ($block->getType() == 'adminhtml/sales_order_view_info'
            && Mage::helper('tnw_salesforce')->isWorking()
        ) {
            $normalOutput = $observer->getTransport()->getHtml();
            $newBlock = $block->getLayout()->createBlock(
                'TNW_Salesforce_Block_Sales_Order_View_Salesforce',
                'salesforce_info',
                array('template' => 'salesforce/sales/order/view/salesforce.phtml')
            )->toHtml();

            $observer->getTransport()->setHtml($newBlock . $normalOutput);
        }
        */
    }
}