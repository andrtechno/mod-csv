<?php

use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use panix\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;

/**
 * @var $client \Google\Client
 */
$client = $model->getGoogleClient();

?>
<?php if (Yii::$app->session->hasFlash('success') && $flashed = Yii::$app->session->getFlash('success')) { ?>
    <?php if (is_array($flashed)) { ?>
        <?php foreach ($flashed as $flash) { ?>
            <div class="alert alert-success"><?= $flash; ?></div>
        <?php } ?>
    <?php } else { ?>
        <div class="alert alert-success"><?= $flashed; ?></div>
    <?php } ?>
<?php } ?>


<?php if (Yii::$app->session->hasFlash('error') && $flashed = Yii::$app->session->getFlash('error')) { ?>
    <?php if (is_array($flashed)) { ?>
        <?php foreach ($flashed as $flash) { ?>
            <div class="alert alert-danger"><?= $flash; ?></div>
        <?php } ?>
    <?php } else { ?>
        <div class="alert alert-danger"><?= $flashed; ?></div>
    <?php } ?>
<?php } ?>
<div class="card">
    <div class="card-header">
        <h5><?= $this->context->pageName ?></h5>
    </div>
    <?php
    $form = ActiveForm::begin([
        'options' => [
            'enctype' => 'multipart/form-data']
    ]);
    ?>
    <div class="card-body">

        <?=
        $form->field($model, 'send_email')
            ->widget(\panix\ext\taginput\TagInput::class, ['placeholder' => 'E-mail'])
            ->hint('Введите E-mail и нажмите Enter');
        ?>

        <?= $form->field($model, 'send_email_warn')->checkbox() ?>
        <?= $form->field($model, 'send_email_error')->checkbox() ?>



        <?= $form->field($model, 'pagenum') ?>
        <?= $form->field($model, 'indent_row') ?>
        <?= $form->field($model, 'indent_column') ?>
        <?=
        $form->field($model, 'ignore_columns')
            ->widget(\panix\ext\taginput\TagInput::class)
            ->hint('Введите буквы и нажмите Enter');
        ?>
        <?php if (YII_DEBUG && false) { ?>
            <div class="text-center mb-4">
                <h4>Google sheets</h4>
            </div>

            <?= $form->field($model, 'google_credentials')->fileInput(['accept' => 'application/json']); ?>



            <?= $form->field($model, 'google_sheet_id')->hint('Разрешите доступ вашей таблице аккаунту '.$client->getConfig('client_email')) ?>
            <?php echo $form->field($model, 'google_sheet_list')->dropDownList($model->getSheetsDropDownList()); ?>
            <?php //echo $form->field($model, 'google_sheet_list'); ?>

        <?php } ?>
    </div>
    <div class="card-footer text-center">
        <?= $model->submitButton(); ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
<?php


//$s = json_decode(file_get_contents($model->getCredentialsPath()));
//\panix\engine\CMS::dump($s);



  //  \panix\engine\CMS::dump($client->getConfig('client_email'));

