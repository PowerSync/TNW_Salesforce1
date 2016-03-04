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
     * @param array $args
     * @return bool
     */
    public function open(array $args = array())
    {
        if (!empty($args['path']) && $this->_allowCreateFolders) {
            $this->checkAndCreateFolder($args['path']);
        }

        if (!is_writable($args['path'])) {
            return false;
        }

        $this->_iwd = getcwd();
        $this->cd(!empty($args['path']) ? $args['path'] : $this->_iwd);
        return true;
    }

    /**
     * @param $offset
     * @param $whence
     * @return int
     */
    public function streamFseek($offset, $whence)
    {
        return @fseek($this->_streamHandler, $offset, $whence);
    }

    /**
     * @return int
     */
    public function streamFtell()
    {
        return @ftell($this->_streamHandler);
    }
}