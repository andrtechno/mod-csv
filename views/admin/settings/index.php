<?php

use panix\engine\Html;
use yii\widgets\ActiveForm;
use panix\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;
?>
<?php
$form = ActiveForm::begin([
            //  'id' => 'form',
            'options' => ['class' => 'form-horizontal'],
            'fieldConfig' => [
                'template' => "{label}\n<div class=\"col-sm-7\">{input}</div>\n<div class=\"col-sm-7 col-sm-offset-5\">{error}</div>",
                'labelOptions' => ['class' => 'col-sm-5 control-label'],
            ],
        ]);
?>
<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><?= $this->context->pageName ?></h3>
    </div>
    <div class="panel-body">
        <?= $form->field($model, 'pagenum') ?>


<?= $form->field($model, 'use_type')->dropDownList(ArrayHelper::map(ProductType::find()->all(), 'id', 'name'), [
    'prompt' => 'Укажите производителя'
]); ?>
Если не выбрать тип, то параметр "<b>type</b>" станет обязательный для csv файла.
    </div>
    <div class="panel-footer text-center">

        <?= Html::submitButton(Html::icon('check') . ' ' . Yii::t('app', 'SAVE'), ['class' => 'btn btn-success']) ?>

    </div>
</div>
<?php ActiveForm::end(); ?>