<?php

class Salesforce_UserTerritoryDeleteHeader
{
    public $transferToUserId;

    public function __construct($transferToUserId)
    {
        $this->transferToUserId = $transferToUserId;
    }
}
