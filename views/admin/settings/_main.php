<?php
/**
 * @var $form \yii\widgets\ActiveForm
 * @var $model \panix\mod\csv\models\SettingsForm
 * @var $this \yii\web\View
 */
?>

<?= $form->field($model, 'pagenum') ?>
<?= $form->field($model, 'indent_row') ?>
<?= $form->field($model, 'indent_column') ?>


<?=
$form->field($model, 'ignore_columns')
    ->widget(\panix\ext\taginput\TagInput::class)
    ->hint('Введите буквы и нажмите Enter');
?>