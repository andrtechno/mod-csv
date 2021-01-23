<?php

namespace panix\mod\csv\components;

use Yii;
use yii\queue\RetryableJobInterface;
use yii\base\BaseObject;
use yii\helpers\Console;

class QueueExport extends BaseObject implements RetryableJobInterface
{
    public $file;
    public $limit;
    public $offset;

    public $attributes;
    public $query;
    public $test;

    public function execute($queue)
    {

       // $exporter = new Exporter();
       // $exporter->export($this->attributes, $this->query, $this->type);
       // print_r($exporter->rows);die;
        echo $this->test;
       // foreach ($exporter->rows as $row){

       // }
        return true;
    }


    public function getTtr()
    {
        return 15 * 60;
    }

    public function canRetry($attempt, $error)
    {
        return ($attempt < 5) && ($error instanceof TemporaryException);
    }
}