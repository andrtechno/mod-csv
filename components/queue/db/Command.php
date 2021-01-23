<?php

namespace panix\mod\csv\components\queue\db;

use yii\console\Exception;
use yii\queue\db\Command as BaseCommand;

/**
 * Manages application db-queue.
 *
 */
class Command extends BaseCommand
{

    /**
     * Runs all jobs by channel from db-queue. Example run "queue-sheets/run <channel_name>"
     * It can be used as cron job.
     *
     * @param string $channel
     *
     * @return null|int exit code.
     */
    public function actionRun($channel = null)
    {
        if ($channel !== null) {
            $this->queue->channel = $channel;
        }

        return $this->queue->run(false);
    }

    /**
     * Listens db-queue and runs new jobs by channel. Example run "queue-sheets/listen 3 <channel_name>"
     * It can be used as daemon process.
     *
     * @param int $timeout number of seconds to sleep before next reading of the queue.
     * @param string $channel
     * @return null|int exit code.
     * @throws Exception when params are invalid.
     */
    public function actionListen($timeout = 3, $channel = null)
    {
        if (!is_numeric($timeout)) {
            throw new Exception('Timeout must be numeric.');
        }
        if ($timeout < 1) {
            throw new Exception('Timeout must be greater than zero.');
        }
        if ($channel !== null) {
            $this->queue->channel = $channel;
        }
        return $this->queue->run(true, $timeout);
    }

    /**
     * Clears the queue.
     *
     * @since 2.0.1
     */
    public function actionClear()
    {
        if ($this->confirm('Are you sure?')) {
            $this->queue->clear();
        }
    }

    /**
     * Removes a job by id.
     *
     * @param int $id
     * @throws Exception when the job is not found.
     * @since 2.0.1
     */
    public function actionRemove($id)
    {
        if (!$this->queue->remove($id)) {
            throw new Exception('The job is not found.');
        }
    }
}
