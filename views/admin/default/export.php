<?php
use panix\engine\Html;
use panix\mod\shop\models\Manufacturer;
use yii\helpers\ArrayHelper;

/**
 * @var $pages \panix\engine\data\Pagination
 * @var $query \panix\mod\shop\models\query\ProductQuery
 * @var $importer \panix\mod\csv\components\CsvImporter
 */

$this->registerJs('
    $(document).on("change","#manufacturer_id, #type_id", function(){
        var fields = [];
        $.each($("#csv-form").serializeArray(), function(i, field){
            fields[field.name]=field.value;
        });

        delete fields["attributes[]"];
        
        window.location = "/admin/csv/default/export?" + jQuery.param($.extend({}, fields));
    });
');

?>

<?= Html::beginForm('', 'GET', ['id' => 'csv-form']) ?>

<div class="card">
    <div class="card-body">


        <div class="form-group row">
            <div class="col-sm-4"><?= Html::label(Yii::t('shop/Product', 'MANUFACTURER_ID'), 'manufacturer_id', ['class' => 'col-form-label']); ?></div>
            <div class="col-sm-8">
                <?= Html::dropDownList('manufacturer_id', Yii::$app->request->get('manufacturer_id'), ArrayHelper::merge(['all' => 'All'], ArrayHelper::map(Manufacturer::find()->all(), 'id', 'name')), [
                    'prompt' => '---',
                    'id' => 'manufacturer_id',
                    //'onChange' => 'manufacturer(this)',
                    'class' => 'custom-select'
                ]); ?>
            </div>
        </div>
        <div class="form-group row">
            <div class="col-sm-4"><?= Html::label(Yii::t('shop/Product', 'TYPE_ID'), 'type_id', ['class' => 'col-form-label']); ?></div>
            <div class="col-sm-8">
                <?= Html::dropDownList('type_id', Yii::$app->request->get('type_id'), ArrayHelper::merge(['all' => 'All'], ArrayHelper::map(\panix\mod\shop\models\ProductType::find()->all(), 'id', 'name')), [
                    'prompt' => '---',
                    'id' => 'type_id',
                    //'onChange' => 'type(this)',
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
                        'hideOnSinglePage' => false,
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

        <?php
        $groups = [];
        foreach ($importer->getExportAttributes('eav_') as $k => $v) {
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
                <th></th>
                <th><?= Yii::t('app', 'NAME') ?></th>
                <th><?= Yii::t('app', 'DESCRIPTION') ?></th>
            </tr>
            </thead>
            <?php foreach ($groups as $groupName => $group) { ?>
                <tr>
                    <th colspan="3" class="text-center"><?= $groupName; ?></th>
                </tr>
                <?php foreach ($group as $k => $v) { ?>
                    <tr>
                        <td align="left" width="10px">
                            <input type="checkbox" checked name="attributes[]" value="<?= $k; ?>">
                        </td>
                        <td><code style="font-size: inherit"><?= Html::encode($k); ?></code></td>
                        <td><?= $v; ?></td>
                    </tr>
                <?php } ?>
            <?php } ?>
        </table>
        <?= Html::endForm() ?>
    </div>
</div>
