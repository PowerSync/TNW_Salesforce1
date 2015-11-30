<?php
/**
 * Author: Tech-N-Web, LLC (dba PowerSync)
 * Email: support@powersync.biz
 * Developer: Evgeniy Ermolaev
 * Date: 17.11.15
 * Time: 12:34
 */

class TNW_Salesforce_Model_Varien_Io_File extends Varien_Io_File
{

    /**
     * @param $offset
     * @param $whence
     * @return int
     */
    public function streamFseek($offset, $whence)
    {
        return @fseek($this->_streamHandler, $offset, $whence);
    }
}