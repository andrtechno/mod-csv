<?php

namespace panix\mod\csv\controllers\admin;

use panix\engine\CMS;
use PhpOffice\PhpSpreadsheet\Document\Properties;
use Yii;
use yii\data\ArrayDataProvider;
use yii\data\Pagination;
use yii\helpers\FileHelper;
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
                'redirect' => ['/csv/default/import']
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
                'file' => $f,
                'name' => $name,
                'img' => Html::img('/uploads/csv_import_image/' . Yii::$app->user->id . '/' . $name, ['width' => 100])
            ];
        }


        $provider = new ArrayDataProvider([
            'allModels' => $data,
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'attributes' => ['name', 'img'],
            ],
        ]);


        $model = new ImportForm();
        $uploadModel = new UploadForm();
        if ($uploadModel->load(Yii::$app->request->post()) && $uploadModel->validate()) {
            $uploadModel->files = UploadedFile::getInstance($uploadModel, 'files');
            if ($uploadModel->files) {
                $filePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $uploadModel->files->name;
                if ($uploadModel->files->extension == 'zip') {
                    $uploadFiles = $uploadModel->files->saveAs($filePath);
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
                    }
                } elseif (in_array($uploadModel->files->extension, $uploadModel::$extension)) {
                    $filePath = Yii::getAlias(Yii::$app->getModule('csv')->uploadPath) . DIRECTORY_SEPARATOR . $uploadModel->files->name;
                    $uploadModel->files->saveAs($filePath);
                    Yii::$app->session->setFlash('success', Yii::t('csv/default', 'SUCCESS_UPLOAD_IMAGES'));
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
                                Yii::$app->session->addFlash('import-error', Yii::t('csv/default', 'LINE') . ": " . $error['line'] . ". " . $error['error']);
                            else
                                Yii::$app->session->addFlash('import-error', $error['error']);
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
                                Yii::$app->session->addFlash('import-warning', Yii::t('csv/default', 'LINE') . ": " . $warning['line'] . ". " . $warning['error']);
                            else
                                Yii::$app->session->addFlash('import-warning', $warning['error']);
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
                                Yii::$app->session->addFlash('import-error', Yii::t('csv/default', 'LINE') . ": " . $error['line'] . ". " . $error['error']);
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

        if ($model->load(Yii::$app->request->get())) {

            if($model->validate()){


            //if (Yii::$app->request->get('manufacturer_id')) {

            if ($get['FilterForm']['manufacturer_id'] !== '') {
                $manufacturers = explode(',', $model->manufacturer_id);
                $query->applyManufacturers($manufacturers);
            }


            $query->where(['type_id' => $model->type_id]);


            $count = $query->count();
            $pages = new Pagination([
                'totalCount' => $count,
                'pageSize' => $get['FilterForm']['page']
            ]);
            $query->offset($pages->offset);
            $query->limit($pages->limit);
            }else{
                CMS::dump($model->errors);die;
            }
        }



        if (Yii::$app->request->get('attributes')) {
            $exporter->export(
                Yii::$app->request->get('attributes'), $query
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



        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('List');
        $sheet->setCellValue('A1', 'Наименование');
        $sheet->setCellValue('B1', 'Категория');
        $sheet->setCellValue('C1', 'Цена');
        $sheet->setCellValue('D1', 'Тип');


        $sheet->setCellValue('A2', 'Product Name');
        $sheet->setCellValue('B2', 'Category/Subcategory');
        $sheet->setCellValue('C2', '10.99');
        $sheet->setCellValue('D2', 'Product Type');


        $sheet->setCellValue('A3', 'Product Name 2');
        $sheet->setCellValue('B3', 'Category/Subcategory');
        $sheet->setCellValue('C3', '25.99');
        $sheet->setCellValue('D3', 'Product Type');


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
