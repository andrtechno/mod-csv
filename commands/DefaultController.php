<?php

namespace panix\mod\csv\commands;

use panix\engine\CMS;
use panix\engine\console\controllers\ConsoleController;
use panix\mod\csv\components\BaseImporter;
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

        for ($i = 2; $i <= 10000; $i++) {
            $sheet->setCellValue('A' . $i, 'Product Name' . $i);
            $sheet->setCellValue('B' . $i, 'Category/Subcategory');
            $sheet->setCellValue('C' . $i, rand(100, 6000));
            $sheet->setCellValue('D' . $i, 'Шампунь');
            $sheet->setCellValue('E' . $i, "https://via.placeholder.com/800x800.jpg");
            $sheet->setCellValue('F' . $i, CMS::gen(5));
            $sheet->setCellValue('G' . $i, $text);
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


    public function actionGooglesheetsReader()
    {
        $spreadsheetId = '1f296aTp1_I3s2y_5WbycmTEWbHAM8n-d4IDvygz8fgo';
        $client = $this->getGoogleClient();

        $service = new \Google_Service_Sheets($client);


        $spreadsheet = $service->spreadsheets->get($spreadsheetId);


        $spreadsheetProperties = $spreadsheet->getProperties();

        $result = [];
        foreach ($spreadsheet->getSheets() as $sheet) {
            /** @var \Google_Service_Sheets_Sheet $sheet */

            $sheet_id = $sheet->getProperties()->sheetId;
            $sheet_name = $sheet->getProperties()->title;


            $gridProperties = $sheet->getProperties()->getGridProperties();
            $gridProperties->columnCount; // Количество колонок
            $gridProperties->rowCount; // Количество строк

            $range = $sheet_name . '';
            $response = $service->spreadsheets_values->get($spreadsheetId, $range, ['majorDimension' => 'ROWS', 'valueRenderOption' => 'UNFORMATTED_VALUE']); //,['valueRenderOption' => 'FORMATTED_VALUE']


            $rows = $response->getValues();
            $firstRow = $rows[0];
            unset($rows[0]);

            $result[$sheet_name][1] = array_map('mb_strtolower', $firstRow);
            // print_r($result);die;
            foreach ($rows as $row_index => $row) {
                foreach ($firstRow as $key => $item) {
                    $result[$sheet_name][$row_index + 1][mb_strtolower($item)] = (isset($row[$key])) ? $row[$key] : NULL;

                }
                $result[$sheet_name][$row_index + 1]['__hash'] = $this->array_md5($result[$sheet_name][$row_index + 1]);
            }

        }
//print_r($result);die;
        /** @var BaseImporter $importer */
        $importer = Yii::$app->getModule('csv')->getImporter();
        $importer->setColumns($result);
        $importer->validator();
        $importer->import();


        foreach ($result as $type => $items) {
            $importer->line = 1;
            // print_r($items);die;
            unset($items[1]);
            foreach ($items as $row2) {
    $qn=$row2['наименование'].$row2['бренд'];
                // print_r($row2);die;
                $command = Yii::$app->db->createCommand('SELECT * FROM {{%google_sheets_reader}} WHERE hash=:hash AND unique_name=:qn')
                    ->bindParam(':hash', $row2['__hash'])
                    ->bindParam(':qn', $qn);

                $find = $command->queryOne();
                if ($find) {
                    $importer->skipRows[] = $importer->line;
                } else {
                    if (!$importer->hasErrors()) {
                        $row2 = $importer->prepareRow($row2);
                        Yii::$app->db->createCommand()->insert('{{%google_sheets_reader}}', [
                            'hash' => $row2['__hash'],
                            'unique_name' => $qn,
                        ])->execute();
                        unset($row2['__hash']);
                        $importer->execute($row2, $type);
                    } else {
                        print_r($importer->getErrors());
                    }
                }
                $importer->line++;
            }
        }
        print_r($importer->skipRows);
        die;

    }

    private function array_md5(array $array)
    {
        //since we're inside a function (which uses a copied array, not
        //a referenced array), you shouldn't need to copy the array
        array_multisort($array);
        return md5(json_encode($array));
    }

    private function getGoogleClient()
    {

        $config = [];
        $config['credentials'] = Yii::$app->runtimePath . DIRECTORY_SEPARATOR . Yii::$app->settings->get('csv', 'google_credentials');

        if (file_exists($config['credentials'])) {
            try {

                // $config['client_id']='113080523440486524239';
                // $config['client_secret']='5e5d50e9a48361d54557f74a4949fd1b82d61d8e';
                $client = new \Google\Client($config);

                $client->useApplicationDefaultCredentials();
                $client->setApplicationName("Something to do with my representatives");
                $client->setScopes(['https://spreadsheets.google.com/feeds']); //'https://www.googleapis.com/auth/drive',


                if ($client->isAccessTokenExpired()) {
                    $client->refreshTokenWithAssertion();
                }

                return $client;
            } catch (\Google_Service_Exception $e) {
                $error = json_decode($e->getMessage());
                // \panix\engine\CMS::dump($error->error->message);
                return $error;
            }
        } else {
            return false;
        }
    }
}
