<?php

namespace panix\mod\csv\models;

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

    public function rules()
    {
        return [
            [['files'], 'file', 'extensions' => ArrayHelper::merge($this->filesExt, self::$extension), 'maxSize' => self::files_max_size],
        ];
    }

    public function attributeLabels()
    {
        return [
            'files' => Yii::t('csv/default', 'FILES', implode(', ', ArrayHelper::merge($this->filesExt, self::$extension))),
        ];
    }
}