<?php

namespace panix\mod\csv\models;

use panix\engine\SettingsModel;

class SettingsForm extends SettingsModel
{

    protected $module = 'csv';
    public static $category = 'csv';

    public $use_type;
    public $pagenum;

    public function rules()
    {
        return [
            ['use_type', 'string'],
            ['pagenum', 'required'],
        ];
    }

    public static function defaultSettings()
    {
        return [
            'pagenum' => 10,
            'use_type' => ''
        ];
    }
}
