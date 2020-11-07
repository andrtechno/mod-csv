<?php
use panix\engine\Html;

?>
<div class="text-center mb-4">
    <?= Html::img($model['filePath'], ['class' => 'img-thumbnail', 'width' => 150]); ?>
    <div>
        <?= $model['name']; ?>
    </div>
    <div>
        <?= Html::a(Html::icon('delete') . ' '.Yii::t('app/default','DELETE'), ['delete-file', 'file' => $model['name']], ['class' => 'mt-2 btn btn-sm btn-outline-danger']); ?>
    </div>
</div>
