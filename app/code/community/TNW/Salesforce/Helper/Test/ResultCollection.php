<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Test_ResultCollection extends Varien_Data_Collection
{
    /**
     * ensures that the collection size is correctly calculated
     *
     * @return int
     */
    public function getSize()
    {
        return count($this->_items);
    }
}
