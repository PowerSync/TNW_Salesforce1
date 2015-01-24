<?php


/**
 * To be used with the Login operation.
 *
 * @package SalesforceSoapClient
 */
class Salesforce_LoginScopeHeader
{
    // boolean that Indicates whether to update the list of most recently used items (True) or not (False).
    public $organizationId;
    public $portalId;

    public function __construct($orgId = NULL, $portalId = NULL)
    {
        $this->organizationId = $orgId;
        $this->portalId = $portalId;
    }
}