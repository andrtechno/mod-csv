<?php

Yii::import('system.utils.CFileHelper');

/**
 * Class to make easier importing images
 */
class CsvImage extends CUploadedFile {

    private $_name;
    private $_tempName;
    private $_type;
    private $_size;
    private $_error;
    public $isDownloaded = false;

    public function __construct($name, $tempName, $type, $size, $error) {
        $this->_name = $name;
        $this->_tempName = $tempName;
        $this->_type = $type;
        $this->_size = $size;
        $this->_error = $error;
        parent::__construct($name, $tempName, $type, $size, $error);
    }

    /**
     * @param string $image name in ./uploads/importImages/ e.g. somename.jpg
     * @return CsvImage
     */
    public static function create($image) {
        $isDownloaded = substr($image, 0, 5) === 'http:';

        if ($isDownloaded) {
            $tmpName = Yii::getPathOfAlias('application.runtime') . DS . sha1(pathinfo($image, PATHINFO_FILENAME)) . '.' . pathinfo($image, PATHINFO_EXTENSION);

            if ((bool) parse_url($image) && !file_exists($tmpName)) {
                $fileHeader = get_headers($image, 1);
                if ((int) (substr($fileHeader[0], 9, 3)) === 200)
                    file_put_contents($tmpName, file_get_contents($image));
            }
        }
        else
            $tmpName = Yii::getPathOfAlias('webroot.uploads.importImages') . DS . $image;

        if (!file_exists($tmpName))
            return false;

        $result = new CsvImage($image, $tmpName, CFileHelper::getMimeType($tmpName), filesize($tmpName), false);
        $result->isDownloaded = $isDownloaded;
        return $result;
    }

    /**
     * @param string $file
     * @param bool $deleteTempFile
     * @return bool
     */
    public function saveAs($file, $deleteTempFile = false) {
        return copy($this->_tempName, $file);
    }

    public function deleteTempFile() {
        @unlink($this->_tempName);
    }

}
