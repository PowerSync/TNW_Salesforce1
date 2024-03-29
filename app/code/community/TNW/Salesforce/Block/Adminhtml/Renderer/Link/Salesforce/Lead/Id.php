<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

/**
 * @deprecated
 */
class TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Lead_Id extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $websiteId = $row->hasData('website_id')
            ? $row->getData('website_id')
            : Mage::app()->getStore($row->getData('store_id'))->getWebsiteId();

        $_field = $row->getData('salesforce_lead_id');
        $link = Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($websiteId, function () use($_field) {
            return Mage::helper('tnw_salesforce/salesforce_abstract')
                ->generateLinkToSalesforce($_field);
        });

        return sprintf('<span style="font-family: monospace;">%s</span>', $link);
    }
}