<?php

/**
 * Customer resource setup model
 *
 * @category   Mage
 * @package    Mage_Customer
 */
class TNW_Salesforce_Model_Customer_Entity_Setup extends Mage_Customer_Model_Entity_Setup
{

    public function getDefaultEntities()
    {
        $array = parent::getDefaultEntities();

        $array['customer']['attributes']['salesforce_id'] = array(
            'label' => 'Salesforce ID',
            'visible' => true,
            'required' => false,
        );

        return $array;
    }

}
