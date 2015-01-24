<?php

class Salesforce_AllowFieldTruncationHeader
{
    public $allowFieldTruncation;

    public function __construct($allowFieldTruncation)
    {
        $this->allowFieldTruncation = $allowFieldTruncation;
    }
}