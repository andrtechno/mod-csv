<script>
    var testurl = '<?= Yii::app()->request->url; ?>';


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
if (isset($_GET['ShopProduct']['categories'])) {
    $getRequest .= "ShopProduct[categories]=" . $_GET['ShopProduct']['categories'];
}
if (!empty($_GET['manufacturer_id'])) {
    if ($getRequest != "?") {
        $getRequest .= "&";
    }
    $getRequest .= "manufacturer_id=" . $_GET['manufacturer_id'];
}
?>

<?php
if (!Yii::app()->request->isAjaxRequest) {
    Yii::app()->tpl->openWidget(array(
        'title' => $this->pageName,
    ));
}
?>
<?php
$form = $this->beginWidget('CActiveForm', array(
    'id' => 'priceExportForm',
    'htmlOptions' => array('class' => 'form-horizontal')
        ));
?>
<?php
if ($dataProvider) {


    $pages = ceil($dataProvider->totalItemCount / $dataProvider->pagination->pageSize); // кол-во страниц


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
        $this->widget('ext.bootstrap.selectinput.SelectInput', array(
            'name' => 'manufacturer_id',
            'data' => CMap::mergeArray(array('all'=>'Все производители'),Html::listData(ShopManufacturer::model()->findAll(), 'id', 'name')),
            
            'value' => (Yii::app()->request->getParam('manufacturer_id')) ? Yii::app()->request->getParam('manufacturer_id') : null,
            'htmlOptions' => array(
                'onChange' => 'loadFilters(this)',
                'empty' => '--- Выбрать ---'
            )
        ));
        ?>

    </div>
</div>
<?php if ($pages) { ?>
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
        echo '<td align="left">' . CHtml::encode(str_replace('eav_', '', $k)) . '</td>';
        echo '<td align="left">' . $v . '</td>';

        echo '</tr>';
    }
    ?>
</table>


<?php $this->endWidget(); ?>

<?php
if (!Yii::app()->request->isAjaxRequest)
    Yii::app()->tpl->closeWidget();
?>


