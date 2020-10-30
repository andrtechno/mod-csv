<?php

use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use shopium\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;


use Google\Spreadsheet\DefaultServiceRequest;


/**
 * @var \panix\mod\csv\models\SettingsForm $model
 * @var \yii\web\View $this
 */


try {
    $client = $model->getGoogleClient();
    if ($client) {
        // $accessToken = $client->fetchAccessTokenWithAssertion()["access_token"];
        // ServiceRequestFactory::setInstance(
        //     new DefaultServiceRequest($accessToken)
        // );
        // Get our spreadsheet
        //composer require asimlqt/php-google-spreadsheet-client
        /*$spreadsheet = (new Google\Spreadsheet\SpreadsheetService)
            ->getSpreadsheetFeed()
            ->getByTitle('test');

        // Get the first worksheet (tab)
        $worksheets = $spreadsheet->getWorksheetFeed()->getEntries();
        $worksheet = $worksheets[0];


        $listFeed = $worksheet->getListFeed();
        $listFeed->insert([
            'name' => "'". 'Igor',
            'phone' => "'". '2425-245-224545',
            'surname' => "'". 'Orlov',
            'city' => "'". 'Berlin',
            'age' => "'". '35',
            'date' => date_create('now')->format('Y-m-d H:i:s')
        ]);*/

        if ($model->google_sheet_id) {
            $service = new Google_Service_Sheets($client);
//\panix\engine\CMS::dump($service);die;
// Prints the names and majors of students in a sample spreadsheet:
// https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit


            $test = $service->spreadsheets->get($model->google_sheet_id);
            $sheet = $test->getSheets();

            //\panix\engine\CMS::dump($sheet[0]->getProperties());die;
//echo $test->getSpreadsheetUrl();


            $range = $model->google_sheet_list . '';

            $values = [
                [
                    'Наименование',
                    'Фото',
                    'РАЗМЕРНАЯ СЕТКА',
                    'Berlin',
                    'Описание',
                    date('Y-m-d H:i:s')
                ],
                [
                    'Andrew',
                    '2425fadsf3',
                    'Orlov',
                    'Berlin',
                    '35',
                    date('Y-m-d H:i:s')
                ],

            ];
            $body = new Google_Service_Sheets_ValueRange([
                'values' => $values,
            ]);
            $params = [
                'valueInputOption' => 'USER_ENTERED'
            ];

            $result = $service->spreadsheets_values->append($model->google_sheet_id, $range, $body, $params);
        }
        // $get = $service->spreadsheets_values->get($model->google_sheet_id, $range, []);

        //\panix\engine\CMS::dump($get->getValues());


        // printf("%f cells updated.", $result->getUpdates());
        \panix\engine\CMS::dump($result);
        /*$result = $service->spreadsheets_values->update($model->google_sheet_id, $range, $body, $params);
        printf("%d cells updated.", $result->getUpdatedCells());*/
    }
} catch (Google_Service_Exception $e) {
    $error = json_decode($e->getMessage());
    // \panix\engine\CMS::dump($e);

}

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
        <?= $form->field($model, 'pagenum') ?>
        <?= $form->field($model, 'indent_row') ?>
        <?= $form->field($model, 'indent_column') ?>
        <?=
        $form->field($model, 'ignore_columns')
            ->widget(\panix\ext\taginput\TagInput::class)
            ->hint('Введите буквы и нажмите Enter');
        ?>
        <?php if (YII_DEBUG) { ?>
            <div class="text-center mb-4">
                <h4>Google sheets</h4>
            </div>

            <?= $form->field($model, 'google_credentials')->fileInput(['accept' => 'application/json']); ?>



            <?= $form->field($model, 'google_sheet_id') ?>
            <?php ///echo $form->field($model, 'google_sheet_list')->dropDownList($model->getSheetsDropDownList()); ?>
            <?= $form->field($model, 'google_sheet_list'); ?>

        <?php } ?>
    </div>
    <div class="card-footer text-center">
        <?= $model->submitButton(); ?>
    </div>
    <?php ActiveForm::end(); ?>
</div>
