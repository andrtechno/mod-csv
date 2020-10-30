<?php

namespace panix\mod\csv\components;

use yii\base\BaseObject;
use yii\queue\JobInterface;
use Yii;

class QueueImport extends BaseObject implements JobInterface
{
    public $row;
    public function execute($queue)
    {

        //print_r($queue);
        echo 'done import' . PHP_EOL;
        return true;
    }
}