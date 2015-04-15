<?php

class TNW_Salesforce_Model_Fixture_Processor_Salesforce
implements EcomDev_PHPUnit_Model_Fixture_ProcessorInterface
{
    /**
     * Applies data from fixture file
     *
     * @param array[] $data
     * @param string $key
     * @param EcomDev_PHPUnit_Model_FixtureInterface $fixture
     *
     * @return EcomDev_PHPUnit_Model_Fixture_ProcessorInterface
     */
    public function apply(array $data, $key, EcomDev_PHPUnit_Model_FixtureInterface $fixture)
    {
        Mage::register('_fixture_data', $data);
        return $this;
    }

    /**
     * Discards data from fixture file
     *
     * @param array[] $data
     * @param string $key
     * @param EcomDev_PHPUnit_Model_FixtureInterface $fixture
     *
     * @return $this
     */
    public function discard(array $data, $key, EcomDev_PHPUnit_Model_FixtureInterface $fixture)
    {
        Mage::unregister('_fixture_data');
        return $this;
    }

    /**
     * Initializes fixture processor before applying data
     *
     * @param EcomDev_PHPUnit_Model_FixtureInterface $fixture
     * @return $this
     */
    public function initialize(EcomDev_PHPUnit_Model_FixtureInterface $fixture)
    {
        return $this;
    }
}