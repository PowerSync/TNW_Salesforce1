<?php
/**
 * Copyright © 2016 TechNWeb, Inc. All rights reserved.
 * See app/code/community/TNW/TNW_LICENSE.txt for license details.
 */

class TNW_Salesforce_Model_Product extends Mage_Catalog_Model_Product
{
    /**
     * Create duplicate
     *
     * @return Mage_Catalog_Model_Product
     */
    public function duplicate()
    {
        $newProduct     = parent::duplicate();
        $unsetAttribute = array(
            'salesforce_id'           => '',
            'salesforce_pricebook_id' => '',
            'sf_insync'               => 0
        );

        $newProduct->addData($unsetAttribute);
        foreach (array_keys($unsetAttribute) as $attributeCode) {
            $newProduct->getResource()
                ->saveAttribute($newProduct, $attributeCode);
        }

        return $newProduct;
    }
}