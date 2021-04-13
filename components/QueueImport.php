<?php

namespace panix\mod\csv\components;

use Yii;
use yii\queue\RetryableJobInterface;
use yii\base\BaseObject;
use yii\helpers\Console;

/**
 * Class QueueImport
 * @property array $rows
 * @property integer $line
 * @property string $type
 * @property bool $remove_images
 * @package panix\mod\csv\components
 */
class QueueImport extends BaseObject implements RetryableJobInterface
{
    public $rows;
    public $line;
    public $type;
    public $remove_images;

    /**
     * @param \yii\queue\Queue $queue
     * @return bool
     */
    public function execute($queue)
    {
        $importer = new Importer();
        $importer->deleteDownloadedImages = $this->remove_images;
        $i = 0;
        $count = count($this->rows);
        // echo count($this->rows);die;
        $errors = [];
        Console::startProgress($i, $count, $queue->getWorkerPid() . ' - ', 100);
        foreach ($this->rows as $line => $row) {
            $importer->line = $line;
            $row = $importer->prepareRow($row);
            $importer->execute($row, $this->type);
            $i++;
            Console::updateProgress($i, $count, $queue->getWorkerPid() . ' - ');

        }

        $config = Yii::$app->settings->get('csv');
        $emails = explode(',', $config->send_email);

        if (($importer->getErrors() || $importer->getWarnings()) && $emails) {


            $mailer = Yii::$app->mailer;
            $mailer->compose(['html' => Yii::$app->getModule('csv')->mailPath . '/queue-notify.tpl'], [
                'errors' => ($config->send_email_error) ? $importer->getErrors() : false,
                'warnings' => ($config->send_email_warn) ? $importer->getWarnings() : false,
                'type' => $this->type
            ])
                ->setFrom(['noreply@example.com' => 'robot'])
                ->setTo($emails)
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