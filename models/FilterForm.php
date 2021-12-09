<?php

namespace panix\mod\csv\models;

use Yii;
use yii\base\Model;

/**
 * Class FilterForm
 * @property integer $manufacturer_id
 * @package panix\mod\csv\models
 */
class FilterForm extends Model
{

    protected $module = 'csv';
    public static $category = 'csv';

    public $type_id;
    public $manufacturer_id;
    public $format;
    public $language;
    public $page = 250;

    public function rules()
    {
        return [
            [['type_id', 'format', 'page','language'], 'required'],
            [['manufacturer_id', 'page'], 'integer'],
            ['page', 'integer', 'min' => 100, 'max' => 1000],
        ];
    }

    public function attributeLabels()
    {
        return [
            'type_id' => Yii::t('shop/Product', 'TYPE_ID'),
            'language' => Yii::t('csv/default', 'LANGUAGE'),
            'manufacturer_id' => Yii::t('shop/Product', 'MANUFACTURER_ID'),
            'format' => Yii::t('csv/default', 'EXPORT_FORMAT'),
            'page' => Yii::t('csv/default', 'PAGE'),
        ];
    }
}