<?php
use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use panix\engine\CMS;
use yii\widgets\Pjax;
use panix\mod\csv\components\AttributesProcessor;
/**
 * @var $importer \panix\mod\csv\components\Importer
 */
/*
$inputFileName = Yii::getAlias('@runtime').DIRECTORY_SEPARATOR.'tmp.xlsx';

$inputFileName = Yii::getAlias('@runtime').DIRECTORY_SEPARATOR.'tmp.csv';
$spreadsheet  = \PhpOffice\PhpSpreadsheet\IOFactory::load($inputFileName);
$worksheet = $spreadsheet->getActiveSheet();

$rows = [];
$cellsHeaders = [];
foreach ($worksheet->getRowIterator(1,1) as $row) {
    $cellIterator2 = $row->getCellIterator();
    $cellIterator2->setIterateOnlyExistingCells(false); // This loops through all cells,
    foreach ($cellIterator2 as $k=>$cell2) {
        $cellsHeaders[$k] = $cell2->getValue();
    }

}
foreach ($worksheet->getRowIterator(2) as $row) {
    $cellIterator = $row->getCellIterator();
    $cellIterator->setIterateOnlyExistingCells(false); // This loops through all cells,
    $cells = [];
    foreach ($cellIterator as $k=>$cell) {
        $cells[$cellsHeaders[$k]] = $cell->getValue();
    }
    $rows[] = $cells;
}

CMS::dump($rows);die;*/
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
<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5><?= $this->context->pageName; ?></h5>
            </div>
            <div class="card-body">

                <div class="alert alert-info"><?= Yii::t('csv/default', 'IMPORT_ALERT'); ?></div>

                <?php if (Yii::$app->session->hasFlash('import-state')) { ?>
                    <div class="form-group">
                        <div class="alert alert-success">
                            <?php
                            foreach (Yii::$app->session->getFlash('import-state') as $flash) {
                                echo '<div>' . $flash . '</div>';
                            }
                            ?>
                        </div>
                    </div>
                <?php } ?>

                <?php if (Yii::$app->session->hasFlash('import-error')) { ?>
                    <div class="form-group">
                        <div class="errorSummary alert alert-danger"><p><?= Yii::t('csv/default', 'ERRORS_IMPORT'); ?>
                                :</p>
                            <ul>
                                <?php
                                foreach (Yii::$app->session->getFlash('import-error') as $flash) {
                                    echo '<li>' . $flash . '</li>';
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                <?php } ?>


                <?php if (Yii::$app->session->hasFlash('import-warning')) { ?>
                    <div class="form-group">
                        <div class="errorSummary alert alert-warning">
                            <p><?= Yii::t('csv/default', 'WARNING_IMPORT'); ?></p>
                            <ul>
                                <?php
                                foreach (Yii::$app->session->getFlash('import-warning') as $flash) {
                                    echo '<li>' . $flash . '</li>';
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                <?php } ?>




                <?php
                $form = ActiveForm::begin([
                    'options' => ['enctype' => 'multipart/form-data'],
                    'fieldConfig' => [
                        'template' => "<div class=\"col-sm-4 col-lg-4\">{label}</div>\n{beginWrapper}\n{input}\n{hint}\n{error}\n{endWrapper}",
                        'horizontalCssClasses' => [
                            'label' => 'col-form-label',
                            'offset' => 'offset-sm-4 offset-lg-4',
                            'wrapper' => 'col-sm-8 col-lg-8',
                        ],
                    ]
                ]);
                echo $form->field($model, 'filename')
                    ->fileInput(['multiple' => false])
                    ->hint(Yii::t('csv/default', 'FILE_INPUT_HINT', [CMS::fileSize($model::file_csv_max_size), implode(', ', $model::$extension)]));
                echo $form->field($model, 'remove_images')->checkbox([]);
                //echo $form->field($model, 'db_backup')->checkbox([]);
                ?>
                <div class="form-group text-center">
                    <?= Html::submitButton(Yii::t('csv/default', 'IMPORT'), ['class' => 'btn btn-success']); ?>
                </div>
                <?php ActiveForm::end(); ?>

                <div class="form-group row">
                    <div class="col">
                        <div class="importDescription alert alert-info">
                            <ul>
                                <li><?= Yii::t('csv/default', 'IMPORT_INFO1') ?></li>
                                <li><?= Yii::t('csv/default', 'IMPORT_INFO2', implode(', ', $importer->required)) ?></li>
                                <li><?= Yii::t('csv/default', 'IMPORT_INFO3', $importer->delimiter) ?></li>
                                <li><?= Yii::t('csv/default', 'IMPORT_INFO4') ?></li>
                            </ul>
                            <br/>


                            <div class="dropdown">
                                <button class="btn btn-secondary dropdown-toggle" type="button" id="dropdownSampleFile"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                    <?= Yii::t('csv/default', 'EXAMPLE_FILE') ?>
                                </button>
                                <div class="dropdown-menu" aria-labelledby="dropdownSampleFile">
                                    <?= Html::a('CSV файл', ['sample', 'format' => 'csv'], ['class' => 'dropdown-item']); ?>
                                    <?= Html::a('XLS файл', ['sample', 'format' => 'xls'], ['class' => 'dropdown-item']); ?>
                                    <?= Html::a('XLSX файл', ['sample', 'format' => 'xlsx'], ['class' => 'dropdown-item']); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </div>
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5>Изображения для импорта</h5>
            </div>
            <div class="card-body">
                <?php
                $formUpload = ActiveForm::begin([
                    'options' => ['enctype' => 'multipart/form-data'],
                    'fieldConfig' => [
                        'template' => "<div class=\"col-sm-4 col-lg-4\">{label}</div>\n{beginWrapper}\n{input}\n{hint}\n{error}\n{endWrapper}",
                        'horizontalCssClasses' => [
                            'label' => 'col-form-label',
                            'offset' => 'offset-sm-4 offset-lg-4',
                            'wrapper' => 'col-sm-8 col-lg-8',
                        ],
                    ]
                ]);
                echo $formUpload->field($uploadModel, 'files')->fileInput(['multiple' => false])->hint(Yii::t('csv/default', 'MAX_FILE_SIZE', CMS::fileSize($model::files_max_size)));
                ?>
                <div class="form-group text-center">
                    <?= Html::submitButton(Yii::t('csv/default', 'Загрузить'), ['class' => 'btn btn-success']); ?>
                </div>
                <?php ActiveForm::end(); ?>

                <?php
                Pjax::begin();


                /*$filesData = new \panix\mod\csv\components\CsvDataProvider([
                    'filename' => Yii::getAlias('@runtime/tmp.csv'),

                ]);*/


                echo \panix\engine\grid\GridView::widget([
                    'enableLayout' => false,
                    //'layoutPath' => '@user/views/layouts/_grid_layout',
                    'dataProvider' => $filesData,
                    'layoutOptions' => ['title' => 'Изображения для импорта'],
                    'columns' => [
                        [
                            'class' => 'yii\grid\SerialColumn',
                            'contentOptions' => ['class' => 'text-center'],
                            'headerOptions' => ['class' => 'text-center']
                        ],
                        [
                            'attribute' => 'img',
                            'header' => 'Фото',
                            'format' => 'raw',
                            'headerOptions' => ['class' => 'text-center'],
                            'contentOptions' => ['class' => 'text-center']

                        ],
                        [
                            'attribute' => 'name',
                            'header' => 'Имя файла',
                            'format' => 'raw',
                        ],
                        [
                            'class' => \yii\grid\ActionColumn::class,
                            'template' => '{delete}',
                            'contentOptions' => ['class' => 'text-center'],
                            'headerOptions' => ['class' => 'text-center'],
                            'header' => Yii::t('app/default', 'OPTIONS'),
                            'buttons' => [
                                'delete' => function ($url, $model) {
                                    return Html::a(Html::icon('delete'), ['delete-file', 'file' => $model['name']], ['class' => 'btn btn-sm btn-danger']);
                                }
                            ]
                        ],
                    ]
                ]);
                Pjax::end();

                ?>
            </div>
        </div>


        <div class="card">
            <div class="card-header">
                <h5>Описание</h5>
            </div>
            <div class="card-body">
                <?php
                $groups = [];
                foreach (AttributesProcessor::getImportExportData('eav_') as $k => $v) {
                    if (strpos($k, 'eav_') === false) {
                        $groups['Основные'][$k] = $v;
                    } else {
                        $groups['Атрибуты'][$k] = $v;
                    }
                }
                ?>
                <table class="table table-striped table-bordered">
                    <tr>
                        <th><?= Yii::t('app/default', 'NAME') ?></th>
                        <th><?= Yii::t('app/default', 'DESCRIPTION') ?></th>
                    </tr>
                    <?php foreach ($groups as $groupName => $group) { ?>
                        <tr>
                            <th colspan="2" class="text-center"><?= $groupName; ?></th>
                        </tr>
                        <?php foreach ($group as $k => $v) {
                            $value = in_array($k, $importer->required) ? $k . ' <span class="required">*</span>' : $k;
                            ?>
                            <tr>
                                <td width="200px"><code><?= str_replace('eav_', '', $value); ?></code></td>
                                <td><?= $v; ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>
</div>



