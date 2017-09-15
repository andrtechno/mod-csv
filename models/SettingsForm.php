<?php
namespace panix\mod\csv\models;
class SettingsCsvForm extends panix\engine\SettingsModel {

    protected $module = 'csv';

    public $use_type;
    public $pagenum;

    public function getForm() {
        Yii::import('ext.bootstrap.selectinput.SelectInput');
        return new CMSForm(array(
            'attributes' => array(
                'id' => __CLASS__,
                'class' => 'form-horizontal',
            ),
            'showErrorSummary' => false,
            'elements' => array(
                'use_type' => array(
                    'type' => 'SelectInput',
                    'data'=>Html::listData(ShopProductType::model()->findAll(), 'name', 'name'),
                    'hint'=>'Если не выбрать тип, то параметр "<b>type</b>" станет обязательный для csv файла.',
                    'htmlOptions'=>array('empty'=>Yii::t('app','EMPTY_LIST'))
                ),
                'pagenum' => array(
                    'type' => 'text',
                ),
            ),
            'buttons' => array(
                'submit' => array(
                    'type' => 'submit',
                    'class' => 'btn btn-success',
                    'label' => Yii::t('app', 'SAVE')
                )
            )
                ), $this);
    }

    public function rules() {
        return array(
            array('use_type', 'type', 'type' => 'string'),
            array('pagenum', 'required'),
        );
    }

    public function save($message = true) {
        Yii::app()->settings->set('csv', $this->attributes);
        parent::save($message);
    }

}
