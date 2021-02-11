<?php

namespace panix\mod\csv\components;

use Yii;
use yii\queue\RetryableJobInterface;
use yii\base\BaseObject;
use yii\helpers\Console;

class QueueImport extends BaseObject implements RetryableJobInterface
{
    public $rows;
    public $line;

    /**
     * @param \yii\queue\Queue $queue
     * @return bool
     */
    public function execute($queue)
    {
        $importer = new Importer();
        $i = 0;
        $count = count($this->rows);
       // echo count($this->rows);die;
        $errors = [];
        Console::startProgress($i, $count, $queue->getWorkerPid() . ' - ', 100);
        foreach ($this->rows as $line => $row) {
            $importer->line = $line;
            $row = $importer->prepareRow($row);
            $result = $importer->importRow($row);
            $i++;
            Console::updateProgress($i, $count, $queue->getWorkerPid() . ' - ');

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
        Console::endProgress(false);
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