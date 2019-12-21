<?php
use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use panix\engine\CMS;

/**
 * @var $importer \panix\mod\csv\components\CsvImporter
 */

?>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5><?= $this->context->pageName; ?></h5>
            </div>
            <div class="card-body">
                <div class="col mt-3">
                    <div class="alert alert-warning"><?= Yii::t('csv/default', 'IMPORT_ALERT'); ?></div>
                </div>


                <?php if ($importer->hasErrors()) { ?>
                    <div class="form-group">
                        <div class="errorSummary alert alert-danger"><p><?= Yii::t('csv/default', 'ERRORS_IMPORT'); ?>:</p>
                            <ul>
                                <?php
                                $i = 0;
                                foreach ($importer->getErrors() as $error) {
                                    if ($i < 10) {
                                        if ($error['line'] > 0)
                                            echo "<li>" . Yii::t('csv/default', 'LINE') . ": " . $error['line'] . ". " . $error['error'] . "</li>";
                                        else
                                            echo "<li>" . $error['error'] . "</li>";
                                    } else {
                                        $n = count($importer->getErrors()) - $i;
                                        echo '<li>' . Yii::t('csv/default', 'AND_MORE', $n) . '</li>';
                                        break;
                                    }
                                    $i++;
                                }
                                ?>
                            </ul>
                        </div>
                    </div>
                <?php } ?>
                <?php if ($importer->stats['create'] > 0 OR $importer->stats['update'] > 0) { ?>
                    <div class="form-group">
                        <div class="successSummary alert alert-info">
                            <?php echo Yii::t('csv/default', 'CREATE_PRODUCTS', $importer->stats['create']); ?>
                            <br/>
                            <?php echo Yii::t('csv/default', 'UPDATE_PRODUCTS', $importer->stats['update']); ?>
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
                echo $form->field($model, 'file_csv')->fileInput(['multiple' => false])->hint(Yii::t('csv/default', 'MAX_FILE_SIZE', CMS::fileSize($model::file_csv_max_size)));
                echo $form->field($model, 'files')->fileInput(['multiple' => false])->hint(Yii::t('csv/default', 'MAX_FILE_SIZE', CMS::fileSize($model::files_max_size)));
                echo $form->field($model, 'remove_images')->checkbox([]);
                echo $form->field($model, 'db_backup')->checkbox([]);
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
                                <li><?= Yii::t('csv/default', 'IMPORT_INFO2') ?></li>
                                <li><?= Yii::t('csv/default', 'IMPORT_INFO3', ['req' => implode(', ', $importer->required)]) ?></li>
                                <li><?= Yii::t('csv/default', 'IMPORT_INFO4') ?></li>
                            </ul>
                            <br/>
                            <a class="btn btn-sm btn-primary"
                               href="<?= \yii\helpers\Url::to('sample') ?>"><?= Yii::t('csv/default', 'EXAMPLE_FILE') ?></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>


    </div>
    <div class="col-lg-6">


        <?= \panix\engine\grid\GridView::widget([
            'dataProvider' => $filesData,
            'layoutOptions' => ['title' => 'Изображения для импорта'],
            'columns' => [
                ['class' => 'yii\grid\SerialColumn'],
                [
                    'attribute' => 'img',
                    'format' => 'raw',
                    'contentOptions' => ['class' => 'text-center']

                ],
                [
                    'attribute' => 'name',
                    'format' => 'raw',
                ],
                [
                    'class' => \yii\grid\ActionColumn::class,
                    'template' => '{delete}',
                    'contentOptions' => ['class' => 'text-center'],
                    'header' => Yii::t('app', 'OPTIONS'),
                    'buttons' => [
                        'delete' => function ($url, $model) {
                            return Html::a(Html::icon('delete'), ['delete-file', 'file' => $model['name']], ['class' => 'btn btn-sm btn-danger']);
                        }
                    ]
                ],
            ]
        ]); ?>


        <div class="card">
            <div class="card-header">
                <h5>Описание</h5>
            </div>
            <div class="card-body">

                <?php
                $shop_config = Yii::$app->settings->get('shop');
                if (isset($shop_config->auto_gen_url)) {
                    ?>
                    <div class="form-group">
                        <div class="alert alert-warning">
                            Интернет магазин использует функцию авто генерации название товара
                        </div>
                    </div>
                <?php } ?>
                <?php
                $groups = [];


                foreach ($importer->getImportableAttributes('eav_') as $k => $v) {
                    if (strpos($k, 'eav_') === false) {
                        $groups['Основные'][$k] = $v;
                    } else {
                        $groups['Атрибуты'][$k] = $v;
                    }
                }
                ?>


                <table class="table table-striped table-bordered">
                    <thead>
                    <tr>
                        <th><?= Yii::t('app', 'NAME') ?></th>
                        <th><?= Yii::t('app', 'DESCRIPTION') ?></th>
                    </tr>
                    </thead>
                    <?php foreach ($groups as $groupName => $group) { ?>
                        <tr>
                            <th colspan="2" class="text-center"><?= $groupName; ?></th>
                        </tr>
                        <?php foreach ($group as $k => $v) {
                            $value = in_array($k, $importer->required) ? $k . ' <span class="required">*</span>' : $k;
                            ?>
                            <tr>
                                <td width="200px"><code style="font-size: inherit"><?= $value; ?></code></td>
                                <td><?= $v; ?></td>
                            </tr>
                        <?php } ?>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>
</div>



