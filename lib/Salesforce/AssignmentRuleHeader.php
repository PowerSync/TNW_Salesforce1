<?php

/**
 * To be used with Create and Update operations.
 * Only one attribute can be set at a time.
 *
 * @package SalesforceSoapClient
 */
class Salesforce_AssignmentRuleHeader
{
    // int
    public $assignmentRuleId;
    // boolean
    public $useDefaultRuleFlag;

    /**
     * Constructor.  Only one param can be set.
     *
     * @param int $id AssignmentRuleId
     * @param boolean $flag UseDefaultRule flag
     */
    public function __construct($id = NULL, $flag = NULL)
    {
        if ($id != NULL) {
            $this->assignmentRuleId = $id;
        }
        if ($flag != NULL) {
            $this->useDefaultRuleFlag = $flag;
        }
    }
}
