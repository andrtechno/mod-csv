<?php

namespace panix\mod\csv\commands;

use panix\engine\CMS;
use panix\engine\console\controllers\ConsoleController;
use PhpOffice\PhpSpreadsheet\Document\Properties;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Yii;


//ignore_user_abort(1);
//set_time_limit(0);

class DefaultController extends ConsoleController
{



    public function actionIndex()
    {

        $text = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat. Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.';
        $format = 'xlsx';
       // $fileName = 'sample-big-' . time();
        $fileName = 'sample-big';
        $spreadsheet = new Spreadsheet();

        $props = new Properties();
        $props->setTitle('Sample file');
        $props->setCreator(Yii::$app->name);
        $props->setLastModifiedBy(Yii::$app->name);
        $props->setCompany(Yii::$app->name);
        $props->setDescription("This example {$format} file");
        $props->setCategory('ImportProducts');
        $spreadsheet->setProperties($props);

        //$test = explode(' ',$text);


        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('List');
        $sheet->setCellValue('A1', 'Наименование');
        $sheet->setCellValue('B1', 'Категория');
        $sheet->setCellValue('C1', 'Цена');
        $sheet->setCellValue('D1', 'Тип');
        $sheet->setCellValue('E1', 'Фото');
        $sheet->setCellValue('F1', 'Артикул');
        $sheet->setCellValue('G1', 'Описание');

        for ($i = 2; $i <= 100; $i++) {
            $sheet->setCellValue('A'.$i, 'Product Name'.$i);
            $sheet->setCellValue('B'.$i, 'Category/Subcategory');
            $sheet->setCellValue('C'.$i, rand(100,6000));
            $sheet->setCellValue('D'.$i, 'Шампунь');
            $sheet->setCellValue('E'.$i, "https://via.placeholder.com/800x800.jpg");
            $sheet->setCellValue('F'.$i, CMS::gen(5));
            $sheet->setCellValue('G'.$i, $text);
        }



        if ($format == 'xls') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
            $fileName = $fileName . '.xls';
        } elseif ($format == 'xlsx') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $fileName = $fileName . '.xlsx';
        } else {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $fileName = $fileName . '.csv';
        }

        $tmpFilePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $fileName;

        // $writer->save('php://output');


        $writer->save($tmpFilePath);
    }

}
