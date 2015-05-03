<?php

/**
 * Class TNW_Salesforce_Model_Blocks_Observer
 */
class TNW_Salesforce_Model_Blocks_Observer
{
    public function updateBlocks($observer) {
        if (Mage::helper('tnw_salesforce')->isWorking()) {
            $normalOutput = $observer->getTransport()->getHtml();
            $block = $observer->getBlock();
            $layout = $block->getLayout();
            if ($block->getType() == 'adminhtml/sales_order_view_info') {
                $newBlock = $layout->createBlock(
                    'TNW_Salesforce_Block_Sales_Order_View_Salesforce',
                    'salesforce_info',
                    array('template' => 'salesforce/sales/order/view/salesforce.phtml')
                )->toHtml();

                $observer->getTransport()->setHtml($newBlock . $normalOutput);
            }
        }
    }
}