<?php

namespace panix\mod\csv\controllers\admin;

use yii\data\Pagination;
use panix\mod\csv\components\DatabaseDumper;
use Yii;
use panix\engine\Html;
use panix\mod\csv\components\CsvExporter;
use panix\mod\csv\components\CsvImporter;
use panix\mod\shop\models\Product;
use panix\engine\data\ActiveDataProvider;
use panix\engine\controllers\AdminController;
use yii\web\HttpException;
use yii\web\Response;

ignore_user_abort(1);
set_time_limit(0);

class DefaultController extends AdminController
{

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


        $importer = new CsvImporter;
        $importer->deleteDownloadedImages = Yii::$app->request->post('remove_images');

        if (Yii::$app->request->isPost && isset($_FILES['file'])) {
            $importer->file = $_FILES['file']['tmp_name'];

            if ($importer->validate() && !$importer->hasErrors()) {
                // Create db backup
                if (isset($_POST['create_dump']) && $_POST['create_dump']) {
                    if (is_writable(Yii::getAlias(Yii::$app->db->backupPath))) {
                        Yii::$app->getDb()->export();
                    } else
                        throw new HttpException(503, Yii::t('csv/default', 'ERROR_WRITE_BACKUP'));
                }
                $importer->import();
            }
        }
        return $this->render('import', array(
            'importer' => $importer
        ));
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

        $query = Product::find();
        $pages = false;
        if (Yii::$app->request->get('manufacturer_id')) {

            if (Yii::$app->request->get('manufacturer_id') !== 'all') {

                $manufacturers = explode(',', Yii::$app->request->get('manufacturer_id', ''));
                $query->applyManufacturers($manufacturers);
            }

            $pages = new Pagination([
                'totalCount' => $query->count(),
                'pageSize' => (int)Yii::$app->settings->get('csv', 'pagenum')
            ]);
            $query->offset($pages->offset);
            $query->limit($pages->limit);

        }

        if (Yii::$app->request->get('attributes')) {
            $exporter->export(
                Yii::$app->request->get('attributes'), $query
            );
        }

        return $this->render('export', array(
            'exporter' => $exporter,
            'pages' => $pages,
            'query' => $query,
            'importer' => new CsvImporter,
        ));
    }


    /**
     * Sample csv file
     */
    public function actionSample()
    {
        $response = Yii::$app->response;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->add('Content-type', 'application/octet-stream');
        $response->getHeaders()->add('Content-Disposition', 'attachment; filename=sample.csv');

        $content = '"name";"category";"price";"type"' . PHP_EOL;
        $content .= '"Product Name";"Category/Subcategory";"10.99";"Base Product"' . PHP_EOL;
        return $content;
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
