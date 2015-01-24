<?php

/**
 * This class is used by PackageVersionHeader
 * @see PackageVersionHeader
 */
class Salesforce_PackageVersion
{
    public $majorNumber;
    public $minorNumber;
    public $namespace;

    /**
     * Class constructor.
     *
     * @param int $majorNumber
     * @param int $minorNumber
     * @param string $namespace
     * @return void
     */
    public function __construct($majorNumber, $minorNumber, $namespace)
    {
        $this->majorNumber = $majorNumber;
        $this->minorNumber = $minorNumber;
        $this->namespace = $namespace;
    }
}
