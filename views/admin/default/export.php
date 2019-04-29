<?php
use panix\engine\Html;
use panix\mod\shop\models\Manufacturer;
use yii\helpers\ArrayHelper;

/**
 * @var $pages \panix\engine\data\Pagination
 * @var $query \panix\mod\shop\models\query\ProductQuery
 */
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

<div class="card">
    <div class="card-body">


        <div class="form-group row">
            <div class="col-sm-4"><?= Html::label(Yii::t('shop/Product', 'MANUFACTURER_ID'), 'manufacturer_id',['class'=>'col-form-label']); ?></div>
            <div class="col-sm-8">

                <?= Html::dropDownList('manufacturer_id', (Yii::$app->request->get('manufacturer_id')) ? Yii::$app->request->get('manufacturer_id') : null, ArrayHelper::merge(['all' => 'All'], ArrayHelper::map(Manufacturer::find()->all(), 'id', 'name')), [
                    'prompt' => 'empty',
                    'id' => 'manufacturer_id',
                    'onChange' => 'loadFilters(this)',
                    'class' => 'custom-select'
                ]); ?>

            </div>
        </div>
        <?php if ($pages) { ?>
            <div class="form-group row">
                <div class="col-12">
                    <?php
                    echo \panix\engine\widgets\LinkPager::widget([
                        'pagination' => $pages,
                        'prevPageLabel' => false,
                        'nextPageLabel' => false,
                        'maxButtonCount' => $query->count(),
                        'pageType' => 'button',
                        'pageCssClass' => 'btn btn-sm mb-2 btn-outline-secondary',
                        'activePageCssClass' => '',
                        'options' => [
                            'tag' => 'div'
                        ]
                    ]);
                    ?>
                </div>
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
    </div>
</div>
