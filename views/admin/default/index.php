<?php
use panix\engine\Html;
/*Yii::app()->tpl->openWidget(array(
    'title' => $this->pageName,
));
$this->widget('zii.widgets.jui.CJuiTabs', array(
    'tabs' => array(
        Yii::t('csv/admin', 'IMPORT') => array('ajax' => $this->createUrl('/admin/csv/default/import'), 'id' => 'import'),
        Yii::t('csv/admin', 'EXPORT') => array('ajax' => $this->createUrl('/admin/csv/default/export'), 'id' => 'export'),
    ),
    'options' => array(
        'collapsible' => true,
        'beforeLoad' => 'js:function (e, ui) {
            common.addLoader();
        }',
        'load' => 'js:function(e, ui) {
            common.removeLoader();
        }',
    ),
));
Yii::app()->tpl->closeWidget();*/
?>
<div class="text-center">
<?=Html::a(Yii::t('csv/default', 'IMPORT'),['/admin/csv/default/import'],['class'=>'btn btn-info']);?>

<?=Html::a(Yii::t('csv/default', 'EXPORT'),['/admin/csv/default/export'],['class'=>'btn btn-info']);?>
</div>