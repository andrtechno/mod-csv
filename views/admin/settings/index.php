<?php

use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use panix\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;

?>

<div class="card">
    <div class="card-header">
        <h5><?= $this->context->pageName ?></h5>
    </div>
    <div class="card-body">
        <?php
        $form = ActiveForm::begin([
            //  'id' => 'form',
          //  'options' => ['class' => 'form-horizontal'],
        ]);
        ?>
        <?= $form->field($model, 'pagenum') ?>
        <?= $form->field($model, 'use_type')->dropDownList(ArrayHelper::map(ProductType::find()->all(), 'id', 'name'), [
            'prompt' => 'Укажите тип товара'
        ])->hint('Если не выбрать тип, то параметр "<b>type</b>" станет обязательный для csv файла.'); ?>

        <?php ActiveForm::end(); ?>
    </div>
    <div class="card-footer text-center">

        <?= Html::submitButton(Yii::t('app', 'SAVE'), ['class' => 'btn btn-success']) ?>

    </div>
</div>
