<?php

namespace panix\mod\csv\controllers\admin;

use Yii;
use yii\data\ArrayDataProvider;
use yii\data\Pagination;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;
use panix\engine\Html;
use panix\engine\CMS;
use panix\engine\controllers\AdminController;
use panix\mod\csv\components\CsvExporter;
use panix\mod\csv\components\CsvImporter;
use panix\mod\shop\models\Product;
use panix\mod\csv\models\FilterForm;
use panix\mod\csv\models\ImportForm;


ignore_user_abort(1);
set_time_limit(0);

class DefaultController extends AdminController
{

    public function actions()
    {
        return [
            'delete-file' => [
                'class' => 'panix\engine\actions\RemoveFileAction',
                'path' => '@uploads/csv_import_images',
                'redirect' => ['/admin/csv/default/import']
            ],
        ];
    }

    public function beforeAction($action)
    {
        if (!file_exists(Yii::getAlias('@uploads/csv_import_images'))) {
            FileHelper::createDirectory(Yii::getAlias('@uploads/csv_import_images'));
        }
        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $this->pageName = Yii::t('csv/default', 'IMPORT_PRODUCTS');


        $this->breadcrumbs[] = [
            'label' => Yii::t('shop/default', 'MODULE_NAME'),
            'url' => ['/admin/shop']
        ];
        $this->breadcrumbs[] = $this->pageName;

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
            'url' => ['/admin/csv/default/export'],
            'options' => ['class' => 'btn btn-success']
        ];
        $this->breadcrumbs[] = [
            'label' => Yii::t('shop/default', 'MODULE_NAME'),
            'url' => ['/admin/shop']
        ];
        $this->breadcrumbs[] = $this->pageName;


        $files = \yii\helpers\FileHelper::findFiles(Yii::getAlias('@uploads/csv_import_images'));

        $data = [];
        foreach ($files as $f) {
            $name = basename($f);
            $data[] = [
                'file' => $f,
                'name' => $name,
                'img' => Html::img('/uploads/csv_import_images/' . $name, ['width' => 100])
            ];
        }


        $provider = new ArrayDataProvider([
            'allModels' => $data,
            'pagination' => [
                'pageSize' => 10,
            ],
            'sort' => [
                'attributes' => ['id', 'name'],
            ],
        ]);

        $importer = new CsvImporter;

        $importer->deleteDownloadedImages = Yii::$app->request->post('ImportForm')['remove_images'];


        $model = new ImportForm();

        if ($model->load(Yii::$app->request->post()) && $model->validate()) {


            $model->files = UploadedFile::getInstance($model, 'files');
            if ($model->files) {
                $filePath = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . $model->files->name;
                if ($model->files->extension == 'zip') {
                    $uploadFiles = $model->files->saveAs($filePath);
                    if ($uploadFiles) {
                        if (file_exists($filePath)) {
                            $zipFile = new \PhpZip\ZipFile();
                            $zipFile->openFile($filePath);
                            $extract = $zipFile->extractTo(Yii::getAlias('@uploads/csv_import_images'));
                            if ($extract)
                                unlink($filePath);
                        } else {
                            die('error 01');
                        }
                    }
                } elseif (in_array($model->files->extension, ['jpg', 'jpeg'])) {
                    $filePath = Yii::getAlias('@uploads/csv_import_images') . DIRECTORY_SEPARATOR . CMS::gen(10) . '.' . $model->files->extension;
                    $model->files->saveAs($filePath);
                }

            }
            $model->file_csv = UploadedFile::getInstance($model, 'file_csv');
            if ($model->file_csv) {

                $importer->file = $model->file_csv->tempName;
                if ($importer->validate() && !$importer->hasErrors()) {
                   // Yii::$app->session->addFlash('success','import success');
                    $importer->import();
                }
            }
            //$this->refresh();
        }

        return $this->render('import', [
            'importer' => $importer,
            'model' => $model,
            'filesData' => $provider
        ]);
    }

    /**
     * Export products
     */
    public function actionExport()
    {
        $this->pageName = Yii::t('csv/default', 'EXPORT_PRODUCTS');
        $exporter = new CsvExporter;

        $this->buttons[] = [
            'label' => Yii::t('csv/default', 'IMPORT'),
            'url' => ['/admin/csv/default/import'],
            'options' => ['class' => 'btn btn-success']
        ];

        $this->breadcrumbs[] = [
            'label' => Yii::t('shop/default', 'MODULE_NAME'),
            'url' => ['/admin/shop']
        ];
        $this->breadcrumbs[] = $this->pageName;


        $get = Yii::$app->request->get();
        $model = new FilterForm();
        $query = Product::find();
        $count = 0;
        $pages = false;
        if ($model->load(Yii::$app->request->get()) && $model->validate()) {

            //if (Yii::$app->request->get('manufacturer_id')) {

            if ($get['FilterForm']['manufacturer_id'] !== 'all') {

                $manufacturers = explode(',', $model->manufacturer_id);
                $query->applyManufacturers($manufacturers);
            }

            $query->where(['type_id' => $model->type_id]);
            $count = $query->count();
            $pages = new Pagination([
                'totalCount' => $count,
                'pageSize' => Yii::$app->settings->get('csv', 'pagenum')
            ]);
            $query->offset($pages->offset);
            $query->limit($pages->limit);
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
            'importer' => new CsvImporter,
        ]);
    }


    /**
     * Sample csv file
     */
    public function actionSample()
    {
        // $response = Yii::$app->response;
        //$response->format = Response::FORMAT_RAW;
        //$response->getHeaders()->add('Content-type', 'application/octet-stream');
        // $response->getHeaders()->add('Content-Disposition', 'attachment; filename=sample.csv');

        $content = '"name";"category";"price";"type"' . PHP_EOL;
        $content .= '"Product Name";"Category/Subcategory";"10.99";"Base Product"' . PHP_EOL;


        return \Yii::$app->response->sendContentAsFile($content, 'sample.csv', [
            'mimeType' => 'application/octet-stream',
            //  'inline'   => false
        ]);

        //  return $content;
    }

    public function getAddonsMenu()
    {
        return [
            [
                'label' => Yii::t('app', 'SETTINGS'),
                'url' => ['/admin/csv/settings/index'],
                'icon' => Html::icon('settings'),
            ],
        ];
    }

}
