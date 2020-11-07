<?php

namespace panix\mod\csv\components;

use Yii;
use yii\queue\RetryableJobInterface;
use yii\base\BaseObject;
use yii\helpers\Console;

class QueueExport extends BaseObject implements RetryableJobInterface
{
    public $rows;
    public $line;

    public function execute($queue)
    {

        $exporter = new Exporter();
        $i = 0;
        $count = count($this->rows);
        echo Console::startProgress($i, $count, $queue->getWorkerPid() . ' - ', 100) . PHP_EOL;
        foreach ($this->rows as $line => $row) {
            $exporter->line = $line;
           // $row = $importer->prepareRow($row);
           // $result = $importer->importRow($row);

            $i++;
            echo Console::updateProgress($i, $count, $queue->getWorkerPid() . ' - ') . PHP_EOL;

        }

        echo Console::endProgress(false) . PHP_EOL;
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