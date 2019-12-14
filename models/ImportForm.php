<?php

namespace panix\mod\csv\models;

use Yii;
use yii\base\Model;

/**
 * Class FilterForm
 * @property string $file_csv
 * @property string $files
 * @package panix\mod\csv\models
 */
class ImportForm extends Model
{

    const files_max_size = 1024 * 1024 * 50;
    const file_csv_max_size = 1024 * 1024 * 5;

    protected $filesExt = ['zip', 'jpg', 'jpeg'];

    public $file_csv;
    public $files;
    public $remove_images = true;
    public $db_backup;

    public function rules()
    {
        return [

            [['file_csv'], 'file', 'extensions' => ['csv'], 'maxSize' => self::file_csv_max_size],
            [['files'], 'file', 'extensions' => $this->filesExt, 'maxSize' => self::files_max_size],
            [['remove_images', 'db_backup'], 'boolean'],
            // [['type_id'], 'required'],
            // [['manufacturer_id'], 'integer'],

        ];
    }

    public function attributeLabels()
    {
        return [
            'file_csv' => Yii::t('shop/Product', 'CSV файл'),
            'files' => Yii::t('shop/Product', 'Изображения (' . implode(', ', $this->filesExt) . ')'),
            'remove_images' => Yii::t('shop/Product', 'Удалить загруженные картинки'),
            'db_backup' => Yii::t('shop/Product', 'Создать резервную копию БД.'),
        ];
    }
}