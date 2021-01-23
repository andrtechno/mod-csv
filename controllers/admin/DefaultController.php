<?php

namespace panix\mod\csv\controllers\admin;

use panix\engine\CMS;
use panix\mod\csv\components\ArrayPager;
use panix\mod\csv\components\Helper;
use panix\mod\csv\components\QueueExport;
use panix\mod\shop\models\ProductType;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Document\Properties;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Yii;
use yii\data\ArrayDataProvider;
use yii\data\Pagination;
use yii\helpers\FileHelper;
use yii\queue\Queue;
use yii\web\UploadedFile;
use panix\engine\Html;
use panix\mod\shop\models\Product;
use panix\engine\controllers\AdminController;
use panix\mod\csv\models\UploadForm;
use panix\mod\csv\models\FilterForm;
use panix\mod\csv\models\ImportForm;
use panix\mod\csv\components\Exporter;
use panix\mod\csv\components\Importer;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

ignore_user_abort(1);
set_time_limit(0);

class DefaultController extends AdminController
{

    public function actions()
    {
        return [
            'delete-file' => [
                'class' => 'panix\engine\actions\RemoveFileAction',
                'path' => Yii::$app->getModule('csv')->uploadPath,
                'redirect' => ['/csv/admin/default/import']
            ],
        ];
    }

    public function beforeAction($action)
    {
        $path = Yii::getAlias(Yii::$app->getModule('csv')->uploadPath);
        if (!file_exists($path)) {
            FileHelper::createDirectory($path);
        }
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $this->pageName = Yii::t('csv/default', 'IMPORT_PRODUCTS');


        $this->view->params['breadcrumbs'][] = $this->pageName;

        return $this->render('index');
    }

    /**
     * Import products
     */
    public function actionImport()
    {

        $this->pageName = Yii::t('csv/default', 'IMPORT_PRODUCTS');
        $this->buttons[] = [
            'label' => Yii::t('csv/default', 'EXPORT'),
            'url' => ['/csv/admin/default/export'],
            'options' => ['class' => 'btn btn-success']
        ];
        $this->view->params['breadcrumbs'][] = $this->pageName;


        $files = \yii\helpers\FileHelper::findFiles(Yii::getAlias(Yii::$app->getModule('csv')->uploadPath));

        $data = [];
        foreach ($files as $f) {
            $name = basename($f);
            $data[] = [
                'name' => $name,
                'filePath' => '/uploads/csv_import_image/' . $name,
            ];
        }


        $provider = new ArrayDataProvider([
            'allModels' => $data,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'attributes' => ['name', 'img'],
            ],
        ]);

        $model = new ImportForm();
        $uploadModel = new UploadForm();
        if ($uploadModel->load(Yii::$app->request->post()) && $uploadModel->validate()) {
            $uploadModel->files = UploadedFile::getInstances($uploadModel, 'files');


            if ($uploadModel->files) {

                foreach ($uploadModel->files as $file) {
                    // CMS::dump($file);die;
                    $filePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $file->name;

                    if ($file->extension == 'zip') {
                        $uploadFiles = $file->saveAs($filePath);
                        if ($uploadFiles) {
                            if (file_exists($filePath)) {
                                $zipFile = new \PhpZip\ZipFile();
                                $zipFile->openFile($filePath);
                                $extract = $zipFile->extractTo(Yii::getAlias(Yii::$app->getModule('csv')->uploadPath));
                                if ($extract)
                                    unlink($filePath);

                                Yii::$app->session->setFlash('success', Yii::t('csv/default', 'SUCCESS_UPLOAD_IMAGES'));

                            } else {
                                die('error 01');
                            }
                        } else {
                            die('no save');
                        }
                    } elseif (in_array($file->extension, $uploadModel::$extension)) {
                        $filePath = Yii::getAlias(Yii::$app->getModule('csv')->uploadPath) . DIRECTORY_SEPARATOR . $file->name;

                        if ($file->getHasError()) {
                            die($file->error);
                        }

                        $uploadFiles = $file->saveAs($filePath);
                        if ($uploadFiles) {
                            Yii::$app->session->setFlash('success', Yii::t('csv/default', 'SUCCESS_UPLOAD_IMAGES'));
                        } else {
                            die('eeeeeeeeeeeeee');
                        }

                    }

                }
                return $this->redirect(['import']);
            }
        }

        $importer = new Importer();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {


            $model->filename = UploadedFile::getInstance($model, 'filename');
            $importer->deleteDownloadedImages = $model->remove_images;
            if ($model->filename) {

                $importer->file = $model->filename;

                $errImport = 0;
                $wrnImport = 0;
                if ($importer->validate() && !$importer->hasErrors()) {
                    Yii::$app->session->setFlash('success', Yii::t('csv/default', 'SUCCESS_IMPORT'));
                    $importer->import();

                    foreach ($importer->getErrors() as $error) {

                        if ($errImport < 10) {
                            if ($error['line'] > 0)
                                Yii::$app->session->addFlash('import-error', $error['type'] . ' ' . Yii::t('csv/default', 'LINE', $error['line']) . " " . $error['error']);
                            else
                                Yii::$app->session->addFlash('import-error', $error['type'] . ' ' . $error['error']);
                        } else {
                            $n = count($importer->getErrors()) - $errImport;
                            Yii::$app->session->addFlash('import-error', Yii::t('csv/default', 'AND_MORE', $n));
                            break;
                        }
                        $errImport++;
                    }


                    foreach ($importer->getWarnings() as $warning) {
                        if ($wrnImport < 10) {
                            if ($warning['line'] > 0)
                                Yii::$app->session->addFlash('import-warning', $warning['type'] . ' ' . Yii::t('csv/default', 'LINE', $warning['line']) . " " . $warning['error']);
                            else
                                Yii::$app->session->addFlash('import-warning', $warning['type'] . ' ' . $warning['error']);
                        } else {
                            $n = count($importer->getWarnings()) - $wrnImport;
                            Yii::$app->session->addFlash('import-warning', Yii::t('csv/default', 'AND_MORE', $n));
                            break;
                        }
                        $wrnImport++;
                    }


                    if ($importer->stats['create'] > 0) {
                        Yii::$app->session->addFlash('import-state', Yii::t('csv/default', 'CREATE_PRODUCTS', $importer->stats['create']));
                    }
                    if ($importer->stats['update'] > 0) {
                        Yii::$app->session->addFlash('import-state', Yii::t('csv/default', 'UPDATE_PRODUCTS', $importer->stats['update']));
                    }
                    if ($importer->stats['deleted'] > 0) {
                        Yii::$app->session->addFlash('import-state', Yii::t('csv/default', 'DELETED_PRODUCTS', $importer->stats['deleted']));
                    }


                } else {
                    foreach ($importer->getErrors() as $error) {

                        if ($errImport < 10) {
                            if ($error['line'] > 0)
                                Yii::$app->session->addFlash('import-error', Yii::t('csv/default', 'LINE', $error['line']) . " " . $error['error']);
                            else
                                Yii::$app->session->addFlash('import-error', $error['error']);
                        } else {
                            $n = count($importer->getErrors()) - $errImport;
                            Yii::$app->session->addFlash('import-error', Yii::t('csv/default', 'AND_MORE', $n));
                            break;
                        }
                        $errImport++;
                    }
                }
                return $this->refresh();
            }

        }

        return $this->render('import', [
            'importer' => $importer,
            'model' => $model,
            'uploadModel' => $uploadModel,
            'filesData' => $provider
        ]);
    }

    /**
     * Export products
     */
    public function actionExport()
    {

        $this->pageName = Yii::t('csv/default', 'EXPORT_PRODUCTS');
        $exporter = new Exporter();

        $this->buttons[] = [
            'label' => Yii::t('csv/default', 'IMPORT'),
            'url' => ['/csv/admin/default/import'],
            'options' => ['class' => 'btn btn-success']
        ];
        $this->view->params['breadcrumbs'][] = $this->pageName;


        $get = Yii::$app->request->get();
        $model = new FilterForm();
        $query = Product::find();
        $count = 0;
        $pages = false;
        $type = false;
        if ($model->load(Yii::$app->request->get())) {
            if ($model->validate()) {

                if ($get['FilterForm']['manufacturer_id'] !== '') {
                    $manufacturers = explode(',', $model->manufacturer_id);
                    $query->applyManufacturers($manufacturers);
                }

                //$query->where(['type_id' => $model->type_id]);
                $query->orderBy(['ordern' => SORT_DESC]);
                $count = $query->count();
                $pages = new Pagination([
                    'totalCount' => $count,
                    //'pageSize' => $get['FilterForm']['page'],
                    'pageSize' => Yii::$app->settings->get('csv', 'pagenum')
                ]);
                $query->offset($pages->offset);
                $query->limit($pages->limit);


                $type = ProductType::findOne($model->type_id);


                $list = array_chunk($query->indexBy('id')->all(), 100, true);
                //CMS::dump($list);die;
                // foreach ($list as $index => $items) {
                /** @var Queue $q */
                // print_r($items);
                $q = Yii::$app->queue;
                // $q->push(new QueueExport(['test' => $pages]));
                // }


                //CMS::dump($items);
                //  die;
            }
        }

        $query = Product::find();
        $query->orderBy(['ordern' => SORT_DESC]);
        $count = $query->count();
        $pages = new Pagination([
            'totalCount' => $count,
            //'pageSize' => $get['FilterForm']['page'],
            'pageSize' => Yii::$app->settings->get('csv', 'pagenum')
        ]);
        $query->offset($pages->offset);
        $query->limit($pages->limit);


        // $exporter = new Exporter();
        //$exporter->export(['Наименование','Цена'], $query,'');


        $test = new ArrayPager($pages);
        //$test->hideOnSinglePage = false;


        CMS::dump($test->list());
        /** @var Queue $q */
        $q = Yii::$app->queueSheets; //
        $q->channel = 'export';
        foreach ($test->list() as $page){
            $q->push(new QueueExport([
                'offset' => $page['offset'],'test'=>'export'
            ]));
        }


        $q->channel = 'import';
        foreach ($test->list() as $page){
            $q->push(new QueueExport([
                'offset' => $page['offset'],'test'=>'import'
            ]));
        }
        die;


        if (true) {


            $fileName = 'test20';

            $spreadsheet = new Spreadsheet();

            $props = new Properties();
            $props->setTitle('Sample file');
            $props->setCreator(Yii::$app->name);
            $props->setLastModifiedBy(Yii::$app->name);
            $props->setCompany(Yii::$app->name);
            $props->setDescription("This example file");
            $props->setCategory('ImportProducts');
            $spreadsheet->setProperties($props);

            $listIndex = 0;
            $types = ProductType::find()->all();
            foreach ($types as $type) {
                /** @var ProductType $type */
                if ($listIndex) { //создаем лист
                    // $spreadsheet->createSheet($listIndex);
                    // $spreadsheet->setActiveSheetIndex($listIndex);
                } else {
                    //$spreadsheet->getActiveSheet()->setTitle($type->name);
                }
                //$sheet = $spreadsheet->getActiveSheet()->setTitle($type->name);
                $data2 = Helper::newSpreadsheet($spreadsheet);
                $data2->getSheet($type->name, true);
                //foreach ($type->attributeRelation as $k=>$attribute) {
                //    $sheet->setCellValue(Helper::num2alpha(($k+1)).'1', $attribute->currentAttribute->title);
                //}


                foreach ($type->products as $product_index => $product) {
                    /*$sheet->setCellValue(Helper::num2alpha(1).($product_index+2), $product->name)
                        ->getColumnDimension(Helper::num2alpha(1))
                        ->setAutoSize(true);

                    $sheet->setCellValue(Helper::num2alpha(2).($product_index+2), $product->sku)
                        ->getColumnDimension(Helper::num2alpha(1))
                        ->setAutoSize(true);

                    $sheet->setCellValue(Helper::num2alpha(3).($product_index+2), $product->price)
                        ->getColumnDimension(Helper::num2alpha(1))
                        ->setAutoSize(true);*/

                    $data2->addRow([
                        'Наименование' => $product->name,
                        'Связи' => '',
                        'Лейблы' => '',
                        'Категория' => 'Уход для волос/Окрашивание для волос',
                        'Доп. Категории' => 'Уход для волос;Уход для волос/Окрашивание для волос/безаммиачная краска',
                        'Бренд' => 'CHI',
                        'Артикул' => $product->sku,
                        'Валюта' => 'UAH',
                        'Цена' => $product->price,
                        'Цена закупки' => '204.00',
                        'Конфигурация' => '',
                        'Скидка' => '',
                        'unit' => 'шт.',
                        'switch' => 1,
                        'Фото' => 'WVfdez3l7W.jpg',
                        'Описание' => 'Профессиональная стойкая керамика CHI44, не содержит аммиак.',
                        'Количество' => 1,
                        'Наличие' => 1,
                        'deleted' => '',
                        'Возраст' => '18+',
                        'Назначение' => 'окрашивание, тонирование, мелирование, блеск',
                        'Время применения' => '',
                        'Пол' => 'женский',
                        'Классификация' => '',
                        'Тип волос' => '',
                        'Страна ТМ' => '',
                        'Сделано в' => '',
                        'Объем' => '85 мл',
                        'Количество в упаковке' => '',
                        'Вес' => '',
                        'Код товара' => '',
                        'Состав' => '',
                        'Страна Производителя' => '',
                        'Фото товара' => '',
                        'Критерии' => 'все типы',
                        'Консистенция' => '',
                        'Доп. Категория' => '',
                        'Объем Мл.' => '',
                    ]);

                }
                $data2->setAutoSize();
                $listIndex++;
            }

            //$data2->save(Yii::getAlias('@runtime') . '/'.$fileName, 'Xlsx');
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $fileName = $fileName . '.xlsx';

            $tmpFilePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $fileName;
            // $writer->save($tmpFilePath);

            Helper::save($tmpFilePath, 'Xlsx');
            die;

        }

        if (Yii::$app->request->get('attributes')) {


            $exporter->export(
                Yii::$app->request->get('attributes'), $query, $type
            );
        }

        return $this->render('export', [
            'exporter' => $exporter,
            'pages' => $pages,
            'query' => $query,
            'count' => $count,
            'model' => $model,
            'importer' => new Importer,
        ]);
    }

    /**
     * Sample csv|xls|xlsx file
     *
     * @param string $format Default csv
     * @return \yii\web\Response
     */
    public function actionSample($format = 'csv')
    {
        $fileName = 'sample-' . time();
        $spreadsheet = new Spreadsheet();

        $props = new Properties();
        $props->setTitle('Sample file');
        $props->setCreator(Yii::$app->name);
        $props->setLastModifiedBy(Yii::$app->name);
        $props->setCompany(Yii::$app->name);
        $props->setDescription("This example {$format} file");
        $props->setCategory('ImportProducts');
        $spreadsheet->setProperties($props);


        $data = [
            'test1' => [
                'A2' => 'Product Name2',
                'B2' => 'Category/Subcategory2',
                'C2' => '5.00'
            ],
            'test2' => [
                'A2' => 'Product Name',
                'B2' => 'Category/Subcategory',
                'C2' => '10.99'
            ]
        ];


        $styleHeader = [
            'font' => [
                'bold' => true,
            ],
            //'alignment' => [
            //    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT,
            // ],
            'borders' => [
                'top' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                ],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => [
                    'argb' => 'FF87b5fa',
                ]
                //'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_GRADIENT_LINEAR,
                /*'rotation' => 90,
                'startColor' => [
                    'argb' => 'FFA0A0A0',
                ],
                'endColor' => [
                    'argb' => 'FFFFFFFF',
                ],*/
            ],
        ];


        $styleArray2 = [
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'color' => [
                    'argb' => 'FFa2fa87',
                ]
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_NONE,
                ],
            ],
        ];

        $main_rows = [
            'Наименование',
            'Категория',
            'Цена',
            'Скидка',
            'Лейблы'
        ];
        $i = 0;
        foreach ($data as $name => $items) {

            if ($i) { //создаем лист
                $spreadsheet->createSheet($i);
                $spreadsheet->setActiveSheetIndex($i);
            }
            $sheet = $spreadsheet->getActiveSheet()->setTitle($name);
            if ($sheet) {
                $sheet->getStyle('A1:' . Helper::num2alpha(count($main_rows)) . '1')->applyFromArray($styleHeader);
                foreach ($main_rows as $index => $value) {
                    $sheet->setCellValue(Helper::num2alpha($index + 1) . '1', $value);
                }


                $a = 1;
                $sheet->getStyle('C2')->applyFromArray($styleArray2);
                foreach ($items as $key => $value) {
                    $sheet->setCellValue($key, $value)
                        ->getColumnDimension(Helper::num2alpha($a))
                        ->setAutoSize($value);
                    $a++;
                }
            }
            $i++;
        }

        if ($format == 'xls') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xls($spreadsheet);
            $fileName = $fileName . '.xls';
        } elseif ($format == 'xlsx') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $fileName = $fileName . '.xlsx';
        } elseif ($format == 'html') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Html($spreadsheet);
            $fileName = $fileName . '.html';
        } elseif ($format == 'pdf') {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf($spreadsheet);
            $fileName = $fileName . '.pdf';
        } else {
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Csv($spreadsheet);
            $fileName = $fileName . '.csv';
        }

        $tmpFilePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $fileName;

        // $writer->save('php://output');


        $writer->save($tmpFilePath);
        $filepath = file_get_contents($tmpFilePath);
        unlink($tmpFilePath);
        return \Yii::$app->response->sendContentAsFile($filepath, $fileName, [
            'mimeType' => 'application/octet-stream',
            //  'inline'   => false
        ]);

    }

    public function getAddonsMenu()
    {
        return [
            [
                'label' => Yii::t('app/default', 'SETTINGS'),
                'url' => ['/csv/admin/settings/index'],
                'icon' => Html::icon('settings'),
            ],
        ];
    }

}
