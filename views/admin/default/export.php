<?php
use panix\engine\Html;
use panix\mod\shop\models\Manufacturer;
use panix\mod\shop\models\ProductType;
use yii\helpers\ArrayHelper;
use panix\engine\bootstrap\ActiveForm;
use panix\mod\csv\components\AttributesProcessor;

/**
 * @var $pages \panix\engine\data\Pagination
 * @var $importer \panix\mod\csv\components\Importer
 */

$this->registerJs('
    $(document).on("change","#manufacturer_id, #type_id, #filterform-manufacturer_id, #filterform-type_id, #filterform-format", function(){
        var fields = [];
        $.each($("#csv-form").serializeArray(), function(i, field){
            fields[field.name]=field.value;
        });

        delete fields["attributes[]"];
        
        window.location = "/admin/csv/default/export?" + jQuery.param($.extend({}, fields));
    });
');


?>

<?php //echo Html::beginForm('', 'GET', ['id' => 'csv-form']) ?>

<div class="card">
    <div class="card-body">
        <?php
        $form = ActiveForm::begin(['id' => 'csv-form', 'method' => 'GET']);
        echo $form->field($model, 'manufacturer_id')->dropDownList(ArrayHelper::map(Manufacturer::find()->all(), 'id', 'name'), ['prompt' => '-']);
        echo $form->field($model, 'type_id')->dropDownList(ArrayHelper::map(ProductType::find()->all(), 'id', 'name'), ['prompt' => '-']);
        echo $form->field($model, 'format')->dropDownList(['csv'=>'csv','xls'=>'xls','xlsx'=>'xlsx']);
        echo $form->field($model, 'page')->hiddenInput()->label(false);

        ?>
        <?php if ($count) { ?>

            <div class="form-group row">
                <div class="col-12">
                    <h4><?= Yii::t('csv/default', 'EXPORT_PRODUCTS'); ?></h4>
                    <?php
                    echo \panix\engine\widgets\LinkPager::widget([
                        'pagination' => $pages,
                        'prevPageLabel' => false,
                        'nextPageLabel' => false,
                        'firstPageLabel' => false,
                        'lastPageLabel' => false,
                        'maxButtonCount' => $count,
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

        $type_id = (isset(Yii::$app->request->get('FilterForm')['type_id'])) ? Yii::$app->request->get('FilterForm')['type_id']:null;
        foreach (AttributesProcessor::getImportExportData('eav_', $type_id) as $k => $v) {
        //foreach ($importer->getExportAttributes('eav_', Yii::$app->request->get('type_id')) as $k => $v) {
            if (strpos($k, 'eav_') === false) {
                $groups['Основные'][$k] = $v;
            } else {
                $groups['Атрибуты'][$k] = $v;
            }
        }
        ?>

        <?php if ($count) { ?>
            <table class="table table-striped table-bordered">
                <thead>
                <tr>
                    <th></th>
                    <th><?= Yii::t('app/default', 'NAME') ?></th>
                    <th><?= Yii::t('app/default', 'DESCRIPTION') ?></th>
                </tr>
                </thead>
                <?php foreach ($groups as $groupName => $group) { ?>
                    <tr>
                        <th colspan="3" class="text-center"><?= $groupName; ?></th>
                    </tr>
                    <?php
                    unset($group['delete']);
                    foreach ($group as $k => $v) {
                        $dis = (in_array($k, $importer->required)) ? true : false;
                        //,'readonly'=>$dis,'disabled'=>$dis
                        ?>
                        <tr>
                            <td align="left" width="10px">
                                <?= Html::checkbox('attributes[]', true, ['value' => $k]); ?>

                            </td>
                            <td><code style="font-size: inherit"><?= Html::encode(str_replace('eav_', '', $k)); ?></code></td>
                            <td><?= $v; ?></td>
                        </tr>
                    <?php } ?>
                <?php } ?>
            </table>
        <?php } ?>
        <?php if ($count) { ?>

            <div class="form-group row">
                <div class="col-12">
                    <h4><?= Yii::t('csv/default', 'EXPORT_PRODUCTS'); ?></h4>
                    <?php


                    echo \panix\engine\widgets\LinkPager::widget([
                        'pagination' => $pages,
                        'prevPageLabel' => false,
                        'nextPageLabel' => false,
                        'firstPageLabel' => false,
                        'lastPageLabel' => false,
                        'maxButtonCount' => $count,
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
        echo Html::a(Yii::t('csv/default', 'Экспортировать все товары'), ['export-queue'],['class'=>'btn btn-success']);
        ?>
        <?php if (Yii::$app->request->get('type_id') && false) { ?>
            <div class="form-group text-center">
                <?php
                echo Html::submitButton(Yii::t('csv/default', 'EXPORT_PRODUCTS'), ['class' => 'btn btn-success']);
                ?>
            </div>
        <?php } ?>
        <?php ActiveForm::end(); // Html::endForm() ?>
    </div>
</div>
