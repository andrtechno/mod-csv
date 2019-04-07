<?php
use panix\engine\Html;
?>



<script>
    $(document).on('change', '.btn-file :file', function () {
        var input = $(this),
                numFiles = input.get(0).files ? input.get(0).files.length : 1,
                label = input.val().replace(/\\/g, '/').replace(/.*\//, '');
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
    });
</script>


<?= Html::beginForm('', 'post',['enctype' => 'multipart/form-data', 'class' => 'form-horizontal']) ?>
<?php if ($importer->hasErrors()) { ?>
    <div class="form-group">
        <div class="errorSummary alert alert-danger"><p>Ошибки импорта:</p>
            <ul>
                <?php
                $i = 0;
                foreach ($importer->getErrors() as $error) {
                    if ($i < 10) {
                        if ($error['line'] > 0)
                            echo "<li>" . Yii::t('admin', 'Строка') . ": " . $error['line'] . ". " . $error['error'] . "</li>";
                        else
                            echo "<li>" . $error['error'] . "</li>";
                    }
                    else {
                        $n = count($importer->getErrors()) - $i;
                        echo '<li>' . Yii::t('admin', 'и еще({n}).', array('{n}' => $n)) . '</li>';
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
            <?php echo Yii::t('csv/default', 'CREATE_PRODUCTS', array('{n}' => $importer->stats['create'])); ?><br/>
            <?php echo Yii::t('csv/default', 'UPDATE_PRODUCTS', array('{n}' => $importer->stats['update'])); ?>
        </div>
    </div>
<?php } ?>

<div class="form-group">
    <div class="col-sm-12">
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


<div class="form-group">
    <div class="col-sm-12"><label style="width: 300px"><input type="checkbox" name="create_dump" value="1" /> <?= Yii::t('csv/default', 'DUMP_DB') ?></label></div>
</div>

<div class="form-group">
    <div class="col-sm-12"><label style="width: 300px"><input type="checkbox" name="remove_images" value="1" checked="checked" /> <?= Yii::t('csv/default', 'REMOVE_IMAGES') ?></label></div>
</div>

<?= Html::endForm() ?>
<div class="form-group">
    <div class="importDescription alert alert-info">
        <ul>
            <li><?= Yii::t('csv/default', 'IMPORT_INFO1') ?></li>
            <li><?= Yii::t('csv/default', 'IMPORT_INFO2') ?></li>
            <li><?= Yii::t('csv/default', 'IMPORT_INFO3', array('req' => implode(', ', $importer->required))) ?></li>
            <li><?= Yii::t('csv/default', 'IMPORT_INFO4') ?></li>
        </ul>
        <br/>
        <a class="btn btn-xs btn-info" href="<?= Yii::$app->urlManager->createUrl('sample') ?>"><?= Yii::t('csv/default', 'EXAMPLE_FILE') ?></a>
    </div>
    <?php
    $shop_config = Yii::$app->settings->get('shop');
    if (isset($shop_config->auto_gen_url)) {
        ?>
        <br/>
        <div class="alert alert-warning">
            Интернет магазин использует функцию авто генерации название товара
        </div>
    <?php } ?>
</div>

<table class="table table-bordered table-striped">
    <?php
    foreach ($importer->getImportableAttributes() as $k => $v) {

        $value = in_array($k, $importer->required) ? $k . ' <span class="required">*</span>' : $k;
        echo '<tr>';
        echo '<td width="200px"><b>' . $value . '</b></td>';
        echo '<td>' . Html::decode($v) . '</td>';

        echo '</tr>';
    }
    ?>
</table>


