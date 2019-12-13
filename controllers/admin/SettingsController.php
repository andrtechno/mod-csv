<?php

namespace panix\mod\csv\controllers\admin;

use Yii;
use panix\mod\csv\models\SettingsForm;
use panix\engine\controllers\AdminController;

class SettingsController extends AdminController
{

    public $icon = 'settings';

    public function actionIndex()
    {
        $this->pageName = Yii::t('app', 'SETTINGS');
        $this->breadcrumbs[] = [
            'label' => Yii::t('csv/default', 'MODULE_NAME'),
            'url' => ['/admin/csv']
        ];
        $this->breadcrumbs[] = $this->pageName;
        $model = new SettingsForm;

        if ($model->load(Yii::$app->request->post())) {
            if($model->validate()){
                $model->save();
            }
        }

        return $this->render('index', ['model' => $model]);
    }

}
