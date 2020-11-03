<?php

namespace panix\mod\csv\components;

use yii\base\BaseObject;
use yii\helpers\Console;
use yii\queue\JobInterface;
use Yii;
use yii\queue\RetryableJobInterface;

class QueueImport extends BaseObject implements RetryableJobInterface
{
    public $rows;
    public $line;

    public function execute($queue)
    {

        $importer = new Importer();
        // $importer->importRow($row);
        $i = 0;
        $count = count($this->rows);
        $errors = [];
        Console::startProgress($i, $count, $queue->getWorkerPid() . ' - ', 100) . PHP_EOL;
        foreach ($this->rows as $line => $row) {
            $importer->line = $line;
            $row = $importer->prepareRow($row);
            $result = $importer->importRow($row);

            $i++;
            Console::updateProgress($i, $count, $queue->getWorkerPid() . ' - ') . PHP_EOL;

        }

        if ($importer->getErrors() || $importer->getWarnings()) {
            $mailer = Yii::$app->mailer;
            $mailer->compose(['html' => Yii::$app->getModule('csv')->mailPath . '/queue-notify.tpl'], [
                'errors' => $importer->getErrors(),
                'warnings' => $importer->getWarnings()
            ])
                ->setFrom(['noreply@example.com' => 'robot'])
                ->setTo([Yii::$app->settings->get('app', 'email') => Yii::$app->name])
                ->setSubject(Yii::t('csv/default', 'QUEUE_SUBJECT'))
                ->send();
        }
        Console::endProgress(false) . PHP_EOL;
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