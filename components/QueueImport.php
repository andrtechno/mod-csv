<?php

namespace panix\mod\csv\components;

use yii\base\BaseObject;
use yii\queue\JobInterface;
use Yii;
use yii\queue\RetryableJobInterface;

class QueueImport extends BaseObject implements RetryableJobInterface
{
    public $rows;
    public $row;
    public function execute($queue)
    {

        $importer = new Importer();
       // $importer->importRow($row);
        foreach ($this->rows as $k=>$row){
            $row = $importer->prepareRow($row);
            $res = $importer->importRow($row);
        }

       // print_r($result);
        echo 'done import' . PHP_EOL;
        return true;
    }


    public function getTtr()
    {
        return 2 * 60;
    }

    public function canRetry($attempt, $error)
    {
        return ($attempt < 5) && ($error instanceof TemporaryException);
    }
}