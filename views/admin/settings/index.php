<?php

use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use panix\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;

/**
 *
 */
?>

<div class="card">
    <div class="card-header">
        <h5><?= $this->context->pageName ?></h5>
    </div>
    <?php
    $form = ActiveForm::begin();
    ?>
    <div class="card-body">
        <?= $form->field($model, 'pagenum') ?>
    </div>
    <div class="card-footer text-center">
        <?= $model->submitButton(); ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
