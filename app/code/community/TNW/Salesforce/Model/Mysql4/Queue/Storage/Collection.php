<?php

/**
 * Class TNW_Salesforce_Model_Mysql4_Queue_Storage_Collection
 */
class TNW_Salesforce_Model_Mysql4_Queue_Storage_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{
    const SYNC_ATTEMPT_LIMIT = 3;

    protected function _construct()
    {
        parent::_construct();
        $this->_init('tnw_salesforce/queue_storage');
    }

    /**
     * @param $obId
     * @return $this
     */
    public function addObjectidToFilter($obId)
    {
        $this->getSelect()
            ->where('main_table.object_id = ?', $obId);

        return $this;
    }

    /**
     * @param $sfType
     * @return $this
     */
    public function addSftypeToFilter($sfType)
    {
        $this->getSelect()
            ->where('main_table.sf_object_type = ?', $sfType);

        return $this;
    }

    /**
     * @return $this
     */
    public function addSyncAttemptToFilter()
    {
        $this->getSelect()
            ->where('main_table.sync_attempt <= ?', self::SYNC_ATTEMPT_LIMIT);

        return $this;
    }

    /**
     * add 'status not equal' condition to filter
     *
     * @param $status
     * @return $this
     */
    public function addStatusNoToFilter($status)
    {
        $this->getSelect()
            ->where('main_table.status <> ?', $status);

        return $this;
    }
}