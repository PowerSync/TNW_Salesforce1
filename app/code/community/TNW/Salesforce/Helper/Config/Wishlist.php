<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Helper_Config_Wishlist extends TNW_Salesforce_Helper_Config
{
    const WISHLIST_ENABLED = 'salesforce_customer/wishlist/sync_enable';
    const WISHLIST_CLOSE_DATE = 'salesforce_customer/wishlist/close_date';
    const WISHLIST_STAGE_NAME = 'salesforce_customer/wishlist/stage_name';
    const WISHLIST_ENABLE_ROLE = 'salesforce_customer/wishlist/enable_contact_role';
    const WISHLIST_CONTACT_ROLE = 'salesforce_customer/wishlist/default_contact_role';
    const WISHLIST_OPPORTUNITY_RECORD_TYPE = 'salesforce_customer/wishlist/opportunity_record_type';

    /**
     * @return bool
     */
    public function syncWishlist()
    {
        return $this->getStoreConfig(self::WISHLIST_ENABLED);
    }

    /**
     * @return int
     */
    public function closeDate()
    {
        return $this->getStoreConfig(self::WISHLIST_CLOSE_DATE);
    }

    /**
     * @return string
     */
    public function stageName()
    {
        return $this->getStoreConfig(self::WISHLIST_STAGE_NAME);
    }

    /**
     * @return bool
     */
    public function syncContactRole()
    {
        return $this->getStoreConfig(self::WISHLIST_ENABLE_ROLE);
    }

    /**
     * @return string
     */
    public function contactRole()
    {
        return $this->getStoreConfig(self::WISHLIST_CONTACT_ROLE);
    }

    /**
     * @return string
     */
    public function opportunityRecordType()
    {
        return $this->getStoreConfig(self::WISHLIST_OPPORTUNITY_RECORD_TYPE);
    }
}