<?php
/**
 * @var $form \yii\widgets\ActiveForm
 * @var $model \panix\mod\csv\models\SettingsForm
 * @var $this \yii\web\View
 */
?>
<?=
$form->field($model, 'send_email')
    ->widget(\panix\ext\taginput\TagInput::class, ['placeholder' => 'E-mail'])
    ->hint('Введите E-mail и нажмите Enter');
?>

<?= $form->field($model, 'send_email_warn')->checkbox() ?>
<?= $form->field($model, 'send_email_error')->checkbox() ?>
