<?php

namespace panix\mod\csv\models;

use panix\mod\csv\components\CsvImporter;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

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

    protected $filesExt = ['zip'];

    public $file_csv;
    public $files;
    public $remove_images = true;
    public $db_backup;

    public function rules()
    {
        return [
            [['file_csv'], 'file', 'extensions' => ['csv'], 'maxSize' => self::file_csv_max_size],
            [['files'], 'file', 'extensions' => ArrayHelper::merge($this->filesExt, CsvImporter::$extension), 'maxSize' => self::files_max_size],
            [['remove_images', 'db_backup'], 'boolean'],
        ];
    }

    public function attributeLabels()
    {
        return [
            'file_csv' => Yii::t('csv/default', 'FILE_CSV'),
            'files' => Yii::t('csv/default', 'FILES', implode(', ', ArrayHelper::merge($this->filesExt, CsvImporter::$extension))),
            'remove_images' => Yii::t('csv/default', 'REMOVE_IMAGES'),
            'db_backup' => Yii::t('csv/default', 'DB_BACKUP'),
        ];
    }
}