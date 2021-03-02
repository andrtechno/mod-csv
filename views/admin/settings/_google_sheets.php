<?php

use panix\engine\CMS;

/**
 * @var $form \yii\widgets\ActiveForm
 * @var $model \panix\mod\csv\models\SettingsForm
 * @var $this \yii\web\View
 * @var $client \Google\Client
 */

$client = $model->getGoogleClient();

?>



<?= $form->field($model, 'google_credentials')->fileInput(['accept' => 'application/json']); ?>



<?= $form->field($model, 'google_sheet_id')->hint('Разрешите доступ вашей таблице аккаунту ' . $client->getConfig('client_email')) ?>
<?php echo $form->field($model, 'google_sheet_list')->dropDownList($model->getSheetsDropDownList()); ?>
<?php //echo $form->field($model, 'google_sheet_list'); ?>

