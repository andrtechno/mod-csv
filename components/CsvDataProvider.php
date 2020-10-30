<?php

namespace panix\mod\csv\components;

use yii\data\BaseDataProvider;
use SplFileObject;

class CsvDataProvider extends BaseDataProvider
{
    /**
     * @var string имя CSV-файла для чтения
     */
    public $filename;

    /**
     * @var string|callable имя столбца с ключом или callback-функция, возвращающие его
     */
    public $key;

    /**
     * @var SplFileObject
     */
    protected $fileObject; // с помощью SplFileObject очень удобно искать конкретную строку в файле


    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        // открыть файл
        $this->fileObject = new SplFileObject($this->filename);
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareModels()
    {
        $models = [];
        $pagination = $this->getPagination();

        if ($pagination === false) {
            // в случае отсутствия разбивки на страницы - прочитать все строки
            while (!$this->fileObject->eof()) {
                $models[] = $this->fileObject->fgetcsv();
                $this->fileObject->next();
            }
        } else {
            // в случае, если разбивка на страницы есть - прочитать только одну страницу
            $pagination->totalCount = $this->getTotalCount();
            $this->fileObject->seek($pagination->getOffset());
            $limit = $pagination->getLimit();

            for ($count = 0; $count < $limit; ++$count) {
                $models[] = $this->fileObject->fgetcsv();
                $this->fileObject->next();
            }
        }

        return $models;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareKeys($models)
    {
        if ($this->key !== null) {
            $keys = [];

            foreach ($models as $model) {
                if (is_string($this->key)) {
                    $keys[] = $model[$this->key];
                } else {
                    $keys[] = call_user_func($this->key, $model);
                }
            }

            return $keys;
        } else {
            return array_keys($models);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareTotalCount()
    {
        $count = 0;

        while (!$this->fileObject->eof()) {
            $this->fileObject->next();
            ++$count;
        }

        return $count;
    }
}