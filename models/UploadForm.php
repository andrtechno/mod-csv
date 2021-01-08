<?php

namespace panix\mod\csv\models;

use panix\engine\CMS;
use Yii;
use yii\base\Model;
use yii\helpers\ArrayHelper;

/**
 * Class UploadForm
 * @property string $files
 * @package panix\mod\csv\models
 */
class UploadForm extends Model
{

    const files_max_size = 1024 * 1024 * 50;

    protected $filesExt = ['zip'];
    public static $extension = ['jpg', 'jpeg'];
    public $files;
    public static $maxFiles = 100;

    public function init()
    {
        $core = ini_get('max_file_uploads');
        self::$maxFiles = ($core > 100) ?: $core;
        parent::init();
    }

    public function rules()
    {

        return [
            [['files'], 'file', 'maxFiles' => self::$maxFiles, 'extensions' => ArrayHelper::merge($this->filesExt, self::$extension), 'maxSize' => CMS::convertPHPSizeToBytes(ini_get('upload_max_filesize'))],
        ];
    }

    public function attributeLabels()
    {
        return [
            'files' => Yii::t('csv/default', 'FILES', implode(', ', ArrayHelper::merge($this->filesExt, self::$extension))),
        ];
    }
}