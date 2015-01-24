<?php

/**
 * This file contains three classes.
 * @package SalesforceSoapClient
 */
class Salesforce_CallOptions
{
    public $client;
    public $defaultNamespace;

    public function __construct($client, $defaultNamespace = NULL)
    {
        $this->client = $client;
        $this->defaultNamespace = $defaultNamespace;
    }
}











