<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Mysql4_Wishlist_Collection extends Mage_Wishlist_Model_Mysql4_Wishlist_Collection
{

    /**
     * @return Mage_Customer_Model_Resource_Customer_Collection
     * @throws Mage_Core_Exception
     */
    public function getCustomerInfoCollection()
    {
        /** @var Mage_Customer_Model_Resource_Customer_Collection $collection */
        $collection = Mage::getResourceModel('customer/customer_collection');

        $collection
            ->addNameToSelect()
            ->addAttributeToSelect('email');

        return $collection;
    }

    /**
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function addCustomerInfo()
    {
        $customerCollection = $this->getCustomerInfoCollection();

        $this->getSelect()
            ->joinInner(
            array('customer' => new Zend_Db_Expr('(' . $customerCollection->getSelect() . ')')),
            'customer.entity_id=main_table.customer_id',
            array(
                'email',
                'name'
        ));

        return $this;
    }
}