<?php
namespace panix\mod\csv\models;
class SettingsForm extends \panix\engine\SettingsModel {

    protected $module = 'csv';

    public $use_type;
    public $pagenum;

    public function rules() {
        return [
            ['use_type', 'string'],
            ['pagenum', 'required'],
        ];
    }

}
