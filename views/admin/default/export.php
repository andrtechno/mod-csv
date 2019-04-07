<?php
use panix\engine\Html;
use panix\mod\shop\models\Manufacturer;
use yii\helpers\ArrayHelper;
?>

<script>
    var testurl = '<?= Yii::$app->request->url; ?>';


    function loadFilters(that) {

        if ($(that).val() === '') {
            window.location = '/admin/csv/default/export';
        } else {
            window.location = '/admin/csv/default/export?manufacturer_id=' + $(that).val();
        }

    }

</script>


<?php
$getRequest = '?';
if (isset($_GET['Product']['categories'])) {
    $getRequest .= "Product[categories]=" . $_GET['Product']['categories'];
}
if (!empty($_GET['manufacturer_id'])) {
    if ($getRequest != "?") {
        $getRequest .= "&";
    }
    $getRequest .= "manufacturer_id=" . $_GET['manufacturer_id'];
}
?>

<?= Html::beginForm('', 'post') ?>
<?php
if ($dataProvider) {


    $pages = ceil($dataProvider->totalCount / $dataProvider->pagination->pageSize); // кол-во страниц


/*
    $this->widget('LinkPager', array(
        // 'currentPage'=>$pages->getCurrentPage(),
        'itemCount' => $dataProvider->totalItemCount,
        'pageSize' => $dataProvider->pagination->pageSize,
        'maxButtonCount' => 5,
        'nextPageLabel' => '',
        'prevPageLabel' => '',
        'firstPageLabel' => '',
        'lastPageLabel' => '',
        'header' => '',
        'htmlOptions' => array('class' => 'pagination'),
    ));*/
}
?>
<div class="form-group">
    <div class="col-sm-4">Производитель</div>
    <div class="col-sm-8">

        <?php
        echo Html::dropDownList('manufacturer_id', (Yii::$app->request->get('manufacturer_id')) ? Yii::$app->request->get('manufacturer_id') : null, ArrayHelper::merge(['all'=>'All'],ArrayHelper::map(Manufacturer::find()->all(), 'id', 'name')), ['prompt'=>'empty','onChange' => 'loadFilters(this)']);

        ?>

    </div>
</div>
<?php if (isset($pages)) { ?>
    <div class="col-xs-12">
        <ul class="pagination">
            <?php
            for ($i = 0; $i < $pages; $i++) {
                $page = $i + 1;
                if ($page == 1) {
                    ?>
                    <li>
                        <input type="submit" name="page" value="1" class="btn btn-sm btn-success" />
                    </li>
                <?php } else { ?>
                    <li>
                        <input type="submit" name="page" value="<?= $page ?>" class="btn btn-sm btn-success" />
                    </li>
                <?php } ?>

            <?php } ?>
        </ul>
    </div>
<?php } ?>


<table class="table table-striped table-bordered">
    <thead>
        <tr>
            <th></th>
            <th><?= Yii::t('app', 'NAME') ?></th>
            <th><?= Yii::t('app', 'ID') ?></th>
        </tr>
    </thead>
    <?php
    foreach ($importer->getExportAttributes('eav_') as $k => $v) {
        echo '<tr>';
        echo '<td align="left" width="10px"><input type="checkbox" checked name="attributes[]" value="' . $k . '"></td>';
        echo '<td align="left">' . Html::encode(str_replace('eav_', '', $k)) . '</td>';
        echo '<td align="left">' . $v . '</td>';

        echo '</tr>';
    }
    ?>
</table>




<?= Html::endForm() ?>