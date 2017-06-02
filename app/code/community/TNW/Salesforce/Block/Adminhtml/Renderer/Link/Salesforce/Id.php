<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Id extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $websiteId = $this->getWebsiteId($row);
        $value = $this->_getValue($row);

        $link = Mage::helper('tnw_salesforce/config')->wrapEmulationWebsiteDifferentConfig($websiteId, function () use($value) {
            return Mage::helper('tnw_salesforce/salesforce_abstract')
                ->generateLinkToSalesforce($value);
        });

        return sprintf('<span style="font-family: monospace;">%s</span>', $link);
    }

    /**
     * @param Varien_Object $row
     * @return int|null
     */
    protected function getWebsiteId(Varien_Object $row)
    {
        if ($row->hasData('website_id')) {
            return (int)$row->getData('website_id');
        }

        if ($row->hasData('store_id')) {
            return (int)Mage::app()->getStore($row->getData('store_id'))->getWebsiteId();
        }

        $websites = $row->getData('website_ids');
        if (!empty($websites)) {
            return (int)reset($websites);
        }

        return null;
    }
}