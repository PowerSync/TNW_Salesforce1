<?php


/**
 * To be used with Retrieve, Query, and QueryMore operations.
 *
 * @package SalesforceSoapClient
 */
class Salesforce_QueryOptions
{
    // int - Batch size for the number of records returned in a query or queryMore call. The default is 500; the minimum is 200, and the maximum is 2,000.
    public $batchSize;

    /**
     * Constructor
     *
     * @param int $limit Batch size
     */
    public function __construct($limit)
    {
        $this->batchSize = $limit;
    }
}