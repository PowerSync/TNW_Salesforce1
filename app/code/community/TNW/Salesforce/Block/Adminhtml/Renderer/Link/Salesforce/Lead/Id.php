<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Block_Adminhtml_Renderer_Link_Salesforce_Lead_Id extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    public function render(Varien_Object $row)
    {
        $_field = $row->getData('salesforce_lead_id');
        return '<span style="font-family: monospace;">'.Mage::helper('tnw_salesforce/salesforce_abstract')->generateLinkToSalesforce($_field).'</span>';
    }
}