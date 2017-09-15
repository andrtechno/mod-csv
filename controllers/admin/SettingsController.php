<?php
namespace panix\mod\csv\controllers\admin;
class SettingsController extends \panix\engine\controllers\AdminController {



    public function actionIndex() {
        $this->pageName = Yii::t('app', 'SETTINGS');
        $this->breadcrumbs = array(
            $this->module->name => $this->module->adminHomeUrl,
            $this->pageName
        );

        $model = new SettingsForm;
        $this->buttons = array(
            array('label' => Yii::t('app', 'RESET_SETTINGS'),
                'url' => $this->createUrl('resetSettings', array(
                    'model' => get_class($model),
                )),
                'htmlOptions' => array('class' => 'btn btn-default')
            )
        );
        if (isset($_POST['SettingsForm'])) {
            $model->attributes = $_POST['SettingsForm'];
            if ($model->validate()) {
                $model->save();
                $this->refresh();
            }
        }
        $this->render('index', array('model' => $model));
    }

}
