<?php

use panix\engine\bootstrap\ActiveForm;

/**
 * @var $model \panix\mod\csv\models\SettingsForm
 * @var $this \yii\web\View
 */
?>


<?php if (Yii::$app->session->hasFlash('error') && $flashed = Yii::$app->session->getFlash('error')) { ?>
    <?php if (is_array($flashed)) { ?>
        <?php foreach ($flashed as $flash) { ?>
            <div class="alert alert-danger"><?= $flash; ?></div>
        <?php } ?>
    <?php } else { ?>
        <div class="alert alert-danger"><?= $flashed; ?></div>
    <?php } ?>
<?php } ?>
<?php
$form = ActiveForm::begin([
    'options' => [
        'enctype' => 'multipart/form-data'
    ]
]);
?>
<div class="card">
    <div class="card-header">
        <h5><?= $this->context->pageName ?></h5>
    </div>

    <div class="card-body">

        <?php
        echo yii\bootstrap4\Tabs::widget([
            'items' => [
                [
                    'label' => $model::t('TAB_MAIN'),
                    'content' => $this->render('_main', ['form' => $form, 'model' => $model]),
                    'active' => true,
                ],
                [
                    'label' => $model::t('TAB_QUEUE'),
                    'content' => $this->render('_queue', ['form' => $form, 'model' => $model]),
                ],
                [
                    'label' => 'Google sheets',
                    'content' => $this->render('_google_sheets', ['form' => $form, 'model' => $model]),
                    'visible' => YII_DEBUG,
                ],
            ],
        ]);
        ?>

    </div>
    <div class="card-footer text-center">
        <?= $model->submitButton(); ?>
    </div>

</div>
<?php ActiveForm::end(); ?>

