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

<?php

$service = new Google_Service_Sheets($client);

$spreadsheetId = '1f296aTp1_I3s2y_5WbycmTEWbHAM8n-d4IDvygz8fgo';
$range = 'Косметика!A1:C';

$spreadsheet = $service->spreadsheets->get($spreadsheetId);


$spreadsheetProperties = $spreadsheet->getProperties();
//$s = json_decode(file_get_contents($model->getCredentialsPath()));

foreach ($spreadsheet->getSheets() as $sheet) {
    /** @var Google_Service_Sheets_Sheet $sheet */
    //$response = $service->spreadsheets_values->get($spreadsheetId,$range);
    $sheet_id = $sheet->getProperties()->sheetId;
    $sheet_name = $sheet->getProperties()->title;


    $gridProperties = $sheet->getProperties()->getGridProperties();
    $gridProperties->columnCount; // Количество колонок
    $gridProperties->rowCount; // Количество строк

    $range = $sheet_name . '';
    $response = $service->spreadsheets_values->get($spreadsheetId, $range, ['majorDimension' => 'ROWS', 'valueRenderOption' => 'UNFORMATTED_VALUE']); //,['valueRenderOption' => 'FORMATTED_VALUE']


    $rows = $response->getValues();
    $firstRow = $rows[0];
    unset($rows[0]);

    $result = [];

    foreach ($rows as $row_index => $row) {
        foreach ($firstRow as $key => $item) {
            $result[$row_index][$item] = (isset($row[$key])) ? $row[$key] : NULL;



        }
        $result[$row_index]['__hash'] = md5(\yii\helpers\Json::encode($result[$row_index]));
    }
    CMS::dump($result);
    die;
}


//  \panix\engine\CMS::dump($client->getConfig('client_email'));

