<?php

namespace panix\mod\csv\controllers\admin;


use panix\engine\CMS;
use Yii;
use panix\mod\csv\models\SettingsForm;
use panix\engine\controllers\AdminController;
use yii\web\UploadedFile;

class SettingsController extends AdminController
{

    public $icon = 'settings';

    public function actionIndex()
    {
        $this->pageName = Yii::t('app/default', 'SETTINGS');
        $this->view->params['breadcrumbs'][] = [
            'label' => Yii::t('csv/default', 'MODULE_NAME'),
            'url' => ['/admin/csv']
        ];
        $this->view->params['breadcrumbs'][] = $this->pageName;


        $this->buttons[] = [
            'label' => Yii::t('csv/default', 'EXPORT'),
            'url' => ['/csv/admin/default/export'],
            'options' => ['class' => 'btn btn-success']
        ];
        $this->buttons[] = [
            'label' => Yii::t('csv/default', 'IMPORT'),
            'url' => ['/csv/admin/default/import'],
            'options' => ['class' => 'btn btn-success']
        ];
        $model = new SettingsForm;
        $oldGoogle_credentials = $model->google_credentials;
       // $oldGoogleTokenFile = $model->google_token;
        if ($model->load(Yii::$app->request->post())) {
            $model->google_credentials = UploadedFile::getInstance($model, 'google_credentials');
           // CMS::dump($model->google_credentials);die;
            if ($model->validate()) {
                if ($model->google_credentials) {

                    $model->google_credentials->saveAs(Yii::$app->runtimePath . DIRECTORY_SEPARATOR . $model->google_credentials->name);
                   $model->google_credentials = $model->google_credentials->name;
                } else {
                    $model->google_credentials = $oldGoogle_credentials;
                }

                /*$upload = UploadedFile::getInstance($model, 'google_token');
                if ($upload) {
                    $upload->saveAs(Yii::getAlias('@app') . DIRECTORY_SEPARATOR . 'google_secret.' . $upload->extension);
                    $model->google_token = 'google_secret.' . $upload->extension;
                } else {
                    $model->google_token = $oldGoogleTokenFile;
                }*/

                $model->save();
                Yii::$app->session->setFlash("success", Yii::t('app/default', 'SUCCESS_UPDATE'));
                return $this->refresh();
           // }else{
            //CMS::dump($model->errors);die;
               // Yii::$app->session->setFlash("error", Yii::t('app/default', 'ERROR_UPDATE'));
            }

        }

        return $this->render('index', ['model' => $model]);
    }

}
