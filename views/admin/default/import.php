<?php
use panix\engine\Html;
use panix\engine\bootstrap\ActiveForm;
use panix\engine\CMS;

/**
 * @var $importer \panix\mod\csv\components\CsvImporter
 */

$this->context->pageName = Yii::t('csv/default', 'IMPORT');

$this->registerJs("
    /*$(document).on('change', '.btn-file :file', function () {
        var input = $(this),
            numFiles = input.get(0).files ? input.get(0).files.length : 1,
            label = input.val().replace(/.*\//, '');
        input.trigger('fileselect', [numFiles, label]);
    });

    $(document).ready(function () {
        $('.btn-file :file').on('fileselect', function (event, numFiles, label) {
            var input = $(this).parents('.input-group').find(':text'),
                log = numFiles > 1 ? numFiles + ' files selected' : label;

            if (input.length) {
                input.val(log);
            } else {
                if (log)
                    alert(log);
            }
        });
    });*/
");

?>

<div class="row">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5><?= $this->context->pageName; ?></h5>
            </div>
            <div class="card-body">
                <div class="col mt-3">
                    <div class="alert alert-warning">Перед загрузкой <strong>CSV файла</strong>, необходимо загрузить
                        изображения.
                    </div>
                </div>
                <?php
                $form = ActiveForm::begin(['options' => ['enctype' => 'multipart/form-data']]);
                echo $form->field($model, 'file_csv')->fileInput(['multiple' => false])->hint(Yii::t('csv/default', 'MAX_FILE_SIZE', CMS::fileSize($model::file_csv_max_size)));
                echo $form->field($model, 'files')->fileInput(['multiple' => false])->hint(Yii::t('csv/default', 'MAX_FILE_SIZE', CMS::fileSize($model::files_max_size)));
                echo $form->field($model, 'remove_images')->checkbox(['disabled' => true]);
                echo $form->field($model, 'db_backup')->checkbox();
                ?>
                <?= Html::submitButton(Yii::t('csv/default', 'Загрузить изображения'), ['class' => 'btn btn-success']); ?>

                <?php ActiveForm::end(); // Html::endForm() ?>



                <?= Html::beginForm('', 'post', ['enctype' => 'multipart/form-data', 'class' => '']) ?>
                <?php if ($importer->hasErrors()) { ?>
                    <div class="form-group">
                        <div class="errorSummary alert alert-danger"><p>Ошибки импорта:</p>
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
                                        echo '<li>' . Yii::t('csv/default', 'AND_MORE', ['n' => $n]) . '</li>';
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
                            <?php echo Yii::t('csv/default', 'CREATE_PRODUCTS', ['n' => $importer->stats['create']]); ?>
                            <br/>
                            <?php echo Yii::t('csv/default', 'UPDATE_PRODUCTS', ['n' => $importer->stats['update']]); ?>
                        </div>
                    </div>
                <?php } ?>

                <div class="form-group row">
                    <div class="col-12">
                        <div class="input-group">
            <span class="input-group-btn">
                <span class="btn btn-primary btn-file">
                    <?= Yii::t('csv/default', 'SELECT_FILE') ?> <input type="file" name="file">
                </span>
            </span>
                            <input type="text" class="form-control" readonly>
                            <span class="input-group-btn">
                <input type="submit" value="<?= Yii::t('csv/default', 'START_IMPORT') ?>" class="btn btn-success">
            </span>
                        </div>
                    </div>
                </div>


                <div class="form-group row">
                    <div class="col-12">
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" id="inputGroupFile04"
                                       aria-describedby="inputGroupFileAddon04">
                                <label class="custom-file-label"
                                       for="inputGroupFile04"><?= Yii::t('csv/default', 'SELECT_FILE') ?></label>
                            </div>
                            <div class="input-group-append">
                                <?= Html::submitButton(Yii::t('csv/default', 'Загрузить изображения'), ['class' => 'btn btn-success']); ?>

                            </div>
                        </div>
                    </div>
                </div>


                <div class="form-group row">
                    <div class="col-12">
                        <label style="width: 300px">
                            <input type="checkbox" name="create_dump" value="1" disabled="disabled"/>
                            <?= Yii::t('csv/default', 'DUMP_DB') ?>
                        </label>
                    </div>
                </div>

                <div class="form-group row">
                    <div class="col-12">
                        <label style="width: 300px">
                            <input type="checkbox" name="remove_images" value="1" checked="checked"
                                   disabled="disabled"/>
                            <?= Yii::t('csv/default', 'REMOVE_IMAGES') ?>
                        </label>
                    </div>
                </div>

                <?= Html::endForm() ?>
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
            ]
        ]); ?>


    </div>
    <div class="col-lg-6">
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



