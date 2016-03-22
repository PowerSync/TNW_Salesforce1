<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_License
{

    public function getStatus() {

        $_model = Mage::getSingleton('tnw_salesforce/connection');
        Mage::getSingleton('tnw_salesforce/connection')->initConnection();
        return $_model->checkPackage();
    }

    /**
     * @param $_server
     * @return bool
     */
    public function forceTest($_server = null)
    {
        return $this->getStatus();
    }

}