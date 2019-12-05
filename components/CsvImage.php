<?php

namespace panix\mod\csv\components;

use Yii;
use yii\helpers\FileHelper;
use yii\web\UploadedFile;

/**
 * Class to make easier importing images
 */
class CsvImage extends UploadedFile {

    public $isDownloaded = false;

    public function __construct($name, $tempName, $type, $size, $error) {
        $this->name = $name;
        $this->tempName = $tempName;
        $this->type = $type;
        $this->size = $size;
        $this->error = $error;
        parent::__construct([]);
    }

    /**
     * @param string $image name in ./uploads/importImages/ e.g. somename.jpg
     * @return CsvImage|false
     */
    public static function create($image) {
        $isDownloaded = substr($image, 0, 4) === 'http';

        if ($isDownloaded) {
            $tmpName = Yii::getAlias('@runtime') . DIRECTORY_SEPARATOR . sha1(pathinfo($image, PATHINFO_FILENAME)) . '.' . pathinfo($image, PATHINFO_EXTENSION);

            if ((bool) parse_url($image) && !file_exists($tmpName)) {
                $fileHeader = get_headers($image, 1);
                if ((int) (substr($fileHeader[0], 9, 3)) === 200)
                    file_put_contents($tmpName, file_get_contents($image));
            }
        }
        else
            $tmpName = Yii::getAlias('@uploads/importImages') . DIRECTORY_SEPARATOR . $image;

        if (!file_exists($tmpName))
            return false;

        $result = new CsvImage($image, $tmpName, FileHelper::getMimeType($tmpName), filesize($tmpName), UPLOAD_ERR_OK);
        $result->isDownloaded = $isDownloaded;
        return $result;
    }

    /**
     * @param string $file
     * @param bool $deleteTempFile
     * @return bool
     */
    public function saveAs($file, $deleteTempFile = false) {
        return copy($this->tempName, $file);
    }

    public function deleteTempFile() {
        @unlink($this->tempName);
    }

}
