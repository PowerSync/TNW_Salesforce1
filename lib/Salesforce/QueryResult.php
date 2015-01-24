<?php

class Salesforce_QueryResult
{
    public $queryLocator;
    public $done;
    public $records;
    public $size;

    public function __construct($response)
    {
        $this->queryLocator = $response->queryLocator;
        $this->done = $response->done;
        $this->size = $response->size;

        if ($response instanceof Salesforce_QueryResult) {
            $this->records = $response->records;
        } else {
            $this->records = array();
            if (isset($response->records)) {
                if (is_array($response->records)) {
                    foreach ($response->records as $record) {
                        $sobject = new Salesforce_SObject($record);
                        array_push($this->records, $sobject);
                    };
                } else {
                    $sobject = new Salesforce_SObject($response->records);
                    array_push($this->records, $sobject);
                }
            }
        }
    }
}