<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_License
{
    /**
     * @return bool
     */
    public function getStatus()
    {
        return (bool)Mage::getSingleton('tnw_salesforce/connection')
            ->isConnected();
    }

    /**
     * @param $_server
     * @return bool
     * @deprecated
     */
    public function forceTest($_server = null)
    {
        return $this->getStatus();
    }

}