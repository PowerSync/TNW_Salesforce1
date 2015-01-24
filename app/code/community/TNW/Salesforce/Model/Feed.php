<?php

class TNW_Salesforce_Model_Feed extends Mage_AdminNotification_Model_Feed
{
    const FEED_URL = 'www.powersync.biz/powersync.rss';

    public function getFeedUrl()
    {
        if (is_null($this->_feedUrl)) {
            $this->_feedUrl = (Mage::getStoreConfigFlag(self::XML_USE_HTTPS_PATH) ? 'https://' : 'http://')
                . self::FEED_URL;
        }
        return $this->_feedUrl;
    }

    public function checkUpdate()
    {
        if (($this->getFrequency() + $this->getLastUpdate()) < time()) {
            return $this;
        }

        $feedData = array();

        $feedXml = $this->getFeedData();

        if ($feedXml && $feedXml->channel && $feedXml->channel->item) {
            foreach ($feedXml->channel->item as $item) {
                $feedData[] = array(
                    'severity' => (int)$item->severity,
                    'date_added' => $this->getDate((string)$item->pubDate),
                    'title' => (string)$item->title,
                    'description' => (string)$item->description,
                    'url' => (string)$item->link,
                );
            }
            if ($feedData) {
                Mage::getModel('adminnotification/inbox')->parse(array_reverse($feedData));
            }

        }
        $this->setLastUpdate();

        return $this;
    }
}