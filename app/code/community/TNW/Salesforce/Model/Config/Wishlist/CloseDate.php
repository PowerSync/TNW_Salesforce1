<?php

class TNW_Salesforce_Model_Config_Wishlist_CloseDate
{
    const ONE_WEEK = 1;
    const TWO_WEEK = 2;
    const THREE_WEEK = 3;
    const ONE_MONTH = 4;
    const THREE_MONTH = 5;
    const SIX_MONTH = 6;

    /**
     * @return array
     */
    public function toOptionArray()
    {
        return array(
            array(
                'label' => '1 week',
                'value' => self::ONE_WEEK
            ),
            array(
                'label' => '2 weeks',
                'value' => self::TWO_WEEK
            ),
            array(
                'label' => '3 weeks',
                'value' => self::THREE_WEEK
            ),
            array(
                'label' => '1 month',
                'value' => self::ONE_MONTH
            ),
            array(
                'label' => '3 months',
                'value' => self::THREE_MONTH
            ),
            array(
                'label' => '6 months',
                'value' => self::SIX_MONTH
            ),
        );
    }
}