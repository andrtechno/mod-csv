<?php

namespace panix\mod\csv\components;

use panix\mod\shop\models\Product;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Yii;
use yii\queue\RetryableJobInterface;
use yii\base\BaseObject;
use yii\helpers\Console;

class QueueExport extends BaseObject implements RetryableJobInterface
{
    public $file;
    public $type_id;
    public $type_name;
    public $offset;
    //public $query;
    //public $attributes;
    public $total_products;


    public function execute($queue)
    {
        $settings_key = 'CSV_EXPORT_' . $this->file;
        $query = Product::find()->where(['type_id' => $this->type_id]);
        $query->offset($this->offset);
        $query->limit(Yii::$app->settings->get('csv', 'pagenum'));
        $filePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $this->file;

        $exporter = new Exporter();
        $exporter->query = $query;
        $exporter->exportQueue();
        // unset($exporter->rows[0]);

        $count = count($exporter->rows);

        // print_r($exporter->rows);

        //$spreadsheet = Helper::newSpreadsheet($tmpFilePath);
        //$spreadsheet->getSheet($this->type_name);


        $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader("Xlsx");

        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getSheetByName($this->type_name);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $i = 0;

        //print_r($exporter->rows);
        $indexRow = $highestRow + 1;
        $alpha = 1;
        $totoc = (int)Yii::$app->settings->get('app', $settings_key);
        echo $totoc . PHP_EOL;
        if (Yii::$app->id == 'console') {
            echo 'Load file: ' . $this->file . PHP_EOL;
            echo Console::startProgress($i, $count, $this->type_name . ' - ', 100) . PHP_EOL;
        }

        foreach ($exporter->rows as $key => $row) {
            $alpha = 1;
            foreach ($row as $value) {
                // echo Helper::num2alpha($alpha).($highestRow + $alpha) . PHP_EOL;
                $sheet->setCellValue(Helper::num2alpha($alpha) . $indexRow, $value);
                $alpha++;

            }
            $indexRow++;
            $i++;
            echo Console::updateProgress($i, $count, $this->type_name . ' - ') . PHP_EOL;
        }
        echo ($totoc - $count) . PHP_EOL;
        Yii::$app->settings->set('app', [$settings_key => ($totoc - $count)]);
        foreach (range(1, $alpha) as $columnID) {
            $sheet->getColumnDimension(Helper::num2alpha($columnID))->setAutoSize(true);
        }

        echo Console::endProgress(false) . PHP_EOL;

        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);

        if (!(int)Yii::$app->settings->get('app', $settings_key)) {
            $mailer = Yii::$app->mailer;
            $message = $mailer->compose(['html' => Yii::$app->getModule('csv')->mailPath . '/queue-export.tpl'], [
                'errors' => false,
                'warnings' => false,
                'type' => 'test'
            ]);
            $message->attach($filePath);
            $message->setFrom(['noreply@example.com' => 'robot']);
            $message->setTo(['andrew.panix@gmail.com']);
            $message->setSubject(Yii::t('csv/default', 'Результат экспорта продукции'));
            $send = $message->send();

            if ($send) {
                echo 'Send to email success!';
                Yii::$app->settings->delete('app', $settings_key);
                unlink($filePath);
            }
        }

//



        return true;
    }


    public function execute2($queue)
    {

        $query = Product::find()->where(['type_id' => $this->type_id]);
        $query->offset($this->offset);
        $query->limit(Yii::$app->settings->get('csv', 'pagenum'));


        $exporter = new Exporter();
        $exporter->query = $query;
        $exporter->export($this->attributes, $query, 'asd');
        unset($exporter->rows[0]);

        $count = count($exporter->rows);
        //print_r($exporter->rows);die;
        //print_r($exporter);die;

        $this->spreadsheet = Helper::newSpreadsheet(Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $this->file);
        $this->spreadsheet->getSheet($this->type_name);
        $i = 0;
        echo Console::startProgress($i, $count, $queue->getWorkerPid() . ' - ', 100) . PHP_EOL;
        foreach ($exporter->rows as $type_id => $item) {
            $this->spreadsheet->addRow($item);
            $i++;
            echo Console::updateProgress($i, $count, $queue->getWorkerPid() . ' - ') . PHP_EOL;

            //$data2->getSheet($type->name, true);

        }

        $this->spreadsheet->setAutoSize();
        $tmpFilePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $this->file;
        // $writer->save($tmpFilePath);
        echo Console::endProgress(false) . PHP_EOL;
        $this->spreadsheet->save($tmpFilePath, 'Xlsx');


        return true;
    }


    public function getTtr()
    {
        return 20 * 60;
    }

    public function canRetry($attempt, $error)
    {
        return ($attempt < 5) && ($error instanceof TemporaryException);
    }
}