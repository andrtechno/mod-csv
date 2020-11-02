<?php
use panix\engine\Html;

/**
 * @var \yii\web\View $this
 */

?>
<div class="text-center">
<?=Html::a(Yii::t('csv/default', 'IMPORT'),['/csv/admin/default/import'],['class'=>'btn btn-info']);?>

<?=Html::a(Yii::t('csv/default', 'EXPORT'),['/csv/admin/default/export'],['class'=>'btn btn-info']);?>
</div>