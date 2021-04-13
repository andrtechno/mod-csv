<?php

namespace panix\mod\csv\components;

use Yii;
use yii\base\Component;
use yii\queue\Queue;
use yii\web\UploadedFile;
use panix\engine\CMS;
use panix\mod\images\behaviors\ImageBehavior;
use panix\mod\shop\models\Manufacturer;
use panix\mod\shop\models\Product;
use panix\mod\shop\models\Supplier;
use panix\mod\shop\components\ExternalFinder;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\Currency;
use panix\mod\shop\models\ProductType;


/**
 * Class BaseImporter
 *
 * @property Product $model
 * @property UploadedFile $file
 * @property string $tempFile
 * @property array $skipRows
 * @property int $job_rows
 * @property array $currentRow
 * @property string $subCategoryPattern Pattern
 * @property boolean $deleteDownloadedImages
 * @property resource $fileHandler
 * @property array $columns
 * @property Category|null $rootCategory
 * @property ExternalFinder $external
 * @property array $errors Errors import
 * @property array $warnings Warnings import
 * @property array $categoriesPathCache Caching find category
 * @property array $productTypeCache Caching find product type
 * @property array $manufacturerCache Caching find manufacturer
 * @property array $supplierCache Caching find supplier
 * @property array $currencyCache Caching find currency
 *
 * @package panix\mod\csv\components
 */
class BaseImporter extends Component
{
    public $skipRows = []; //@todo: need test

    /**
     * @var UploadedFile path to file
     */
    public $file;

    /**
     * @var string Temp file in @runtime path
     */
    public $tempFile;

    /**
     * @var string
     */
    private $subCategoryPattern = '/\\/((?:[^\\\\\/]|\\\\.)*)/';

    /**
     * @var bool
     */
    public $deleteDownloadedImages = false;

    /**
     * @var resource
     */
    protected $fileHandler;

    /**
     * Columns from first line. e.g array(category, price, name, etc...)
     * @var array
     */
    protected $columns = [];

    /**
     * @var null|Category
     */
    protected $rootCategory = null;

    /**
     * Caching date
     * @var array
     */
    protected $categoriesPathCache = [],
        $productTypeCache = [],
        $manufacturerCache = [],
        $supplierCache = [],
        $currencyCache = [];


    /**
     * @var int Current line
     */
    public $line = 1;

    /**
     * @var string Current product type
     */
    public $type;

    /**
     * Errors and warnings
     * @var array
     */
    protected $errors = [], $warnings = [];

    /**
     * Insert queue items
     * @var int
     */
    public $job_rows = 75;
    public $isNew = false;
    private $totalProductCount = 0;
    protected $uniqueName;
    /**
     * @var ExternalFinder
     */
    public $external = ExternalFinder::class;

    /**
     * @var array
     */
    public $stats = [
        'create' => 0,
        'update' => 0,
        'deleted' => 0
    ];
    private static $extension = ['jpg', 'jpeg'];
    public $required = ['наименование', 'категория', 'цена'];
    public $currentRow = [];
    public $testParams = [
        'sku' => 'артикул',
        'price' => 'цена',
    ];
    /**
     * @var Product
     */
    public $model;

    public function __construct(array $config = [])
    {
        //$this->external = new ExternalFinder('{{%csv}}');

        if ($this->external) {
            $this->external = Yii::createObject([
                'class' => $this->external,
                'table' => '{{%csv}}'
            ]);
        }
        parent::__construct($config);
    }

    /**
     * Here we go
     */
    public function import()
    {
        // Process lines


        $counter = 0;
        $queueList = [];

        //Remove empty rows
        foreach ($this->columns as $type => $cols) {

            $this->type = $type;
            unset($cols[1]);
            //   foreach ($cols as $col) {
            $this->line = 1;

            foreach ($cols as $columnIndex => $row) {
                $this->line = $columnIndex;

                if (isset($row['наименование'], $row['цена'], $row['категория'])) {

                    $row = array_filter($row, function ($value, $key) {
                        if (in_array($key, $this->required)) {
                            if (empty($value)) {
                                $this->errors[] = [
                                    'line' => $this->line,
                                    'message' => Yii::t('csv/default', 'REQUIRE_COLUMN_EMPTY', ['column' => $key]),
                                    'type' => Yii::t('csv/default', 'LIST', $this->type)
                                ];
                                $this->skipRows[] = $this->line;
                            }
                        }
                        return [$key => $value];
                    }, ARRAY_FILTER_USE_BOTH);

                    if (!in_array($columnIndex, $this->skipRows)) {//if (!$this->errors) {
                        $row = $this->prepareRow($row);
                        if ($counter <= $this->job_rows) {

                            $this->execute($row, $this->type);
                        } else {
                            $queueList[$this->line] = $row;
                        }
                    }

                }

                $counter++;
            }

            if ($queueList) {
                if (Yii::$app->id != 'console') {
                    Yii::$app->session->addFlash('success', Yii::t('csv/default', 'QUEUE_ADD', [
                        'type' => $this->type,
                        'count' => count($queueList)
                    ]));
                }
                $list = array_chunk($queueList, $this->job_rows, true);
                /** @var Queue $q */
                $q = Yii::$app->queue;
                Yii::$app->settings->set('app', ['queue_default' => time()]);
                foreach ($list as $index => $items) {
                    $q->priority($index)->push(new QueueImport([
                        'rows' => $items,
                        'type' => $this->type,
                        'remove_images' => $this->deleteDownloadedImages
                    ]));
                }
            }
            //  }


        }


    }


    private function getFileHandler()
    {
        $config = Yii::$app->settings->get('csv');
        $indentRow = (isset($config->indent_row)) ? $config->indent_row : 1;
        $indentColumn = (isset($config->indent_column)) ? $config->indent_column : 1;
        $ignoreColumns = (isset($config->ignore_columns)) ? explode(',', $config->ignore_columns) : [];


        if ($this->file->extension == 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } elseif ($this->file->extension == 'xls') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        } else {
            die('FATAL ERROR: xlsx, xls');
        }

        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($this->tempFile);


        $result = [];
        $columns = [];

        $worksheets = $spreadsheet->getAllSheets();
        foreach ($worksheets as $worksheet) {

            $title = $worksheet->getTitle();
            $result[$title] = [];

            //$rows = [];
            $cellsHeaders = [];
            foreach ($worksheet->getRowIterator($indentRow, 1) as $k => $row) {
                $cellIterator2 = $row->getCellIterator(Helper::num2alpha($indentColumn));
                $cellIterator2->setIterateOnlyExistingCells(false); // This loops through all cells,
                foreach ($cellIterator2 as $column => $cell2) {
                    $value = trim($cell2->getValue());
                    if (!in_array(mb_strtolower($column), $ignoreColumns)) {
                        if (!empty($value)) {
                            $cellsHeaders[$column] = mb_strtolower($value);
                            $result[$title][1][] = mb_strtolower($value);
                        }
                    }
                }

            }

            foreach ($worksheet->getRowIterator($indentRow + 1) as $column_key => $row) {

                $cellIterator = $row->getCellIterator(Helper::num2alpha($indentColumn));
                $cellIterator->setIterateOnlyExistingCells(false); // This loops through all cells,

                $cells = [];

                foreach ($cellIterator as $column2 => $cell) {
                    $value = trim($cell->getValue());
                    if (isset($cellsHeaders[$column2])) {
                        if (!in_array(mb_strtolower($column2), $ignoreColumns)) {
                            if ($cell->getDataType() == 'f') {
                                preg_match('/(IMAGE).*[\'"](https?:\/\/?.*)[\'"]/iu', $value, $match);
                                if (isset($match[1]) && isset($match[2])) {
                                    if (mb_strtolower($match[1]) == 'image') {

                                        $cells[$cellsHeaders[$column2]] = trim($match[2]);
                                    }
                                }
                            } else {
                                $cells[$cellsHeaders[$column2]] = $value;
                            }
                        }
                    }
                }

                $result[$title][$column_key] = $cells;
            }
            $columns[$title] = $result[$title];// has phpsheet filter by enabled

            /*$columns[$title] = array_filter($result[$title], function ($value) {
                if ($value) {
                    foreach ($value as $row) {
                        if (!is_null($row) && !empty($row)) {
                            return $row;
                        }
                    }
                }
                return [];
            });*/
        }


        // CMS::dump($columns);die;
        return $columns;


    }

    public function validator()
    {
        $i = 1;
        foreach (AttributesProcessor::getImportExportData('eav_') as $key => $value) {
            if (mb_strpos($key, 'eav_') !== false) {
                $attributeName = str_replace('eav_', '', $key);
                if (in_array($attributeName, AttributesProcessor::skipNames)) {
                    $this->errors[] = [
                        'line' => 0,
                        'message' => Yii::t('csv/default', 'ERROR_COLUMN_ATTRIBUTE', [
                            'attribute' => $attributeName
                        ]),
                        'type' => Yii::t('csv/default', 'LIST', $this->type)
                    ];
                    return false;
                }
            }
            $i++;
        }

        foreach ($this->required as $column) {

            foreach ($this->columns as $listName => $col) {

                if (!in_array($column, $col[1])) {


                    $this->errors[] = [
                        'line' => 0,
                        'message' => Yii::t('csv/default', 'REQUIRE_COLUMN', [
                            'column' => $column,
                            'type' => Yii::t('csv/default', 'LIST', $listName)
                        ])
                    ];
                }
            }

        }

        return !$this->hasErrors();
    }

    /**
     * @return bool validate file
     */
    public function validate()
    {

        $this->totalProductCount = Product::find()->count();
        // Check file exists and readable
        if (is_uploaded_file($this->file->tempName)) {

            $newDir = Yii::getAlias('@runtime') . '/tmp.' . $this->file->extension;
            move_uploaded_file($this->file->tempName, $newDir);
            $this->tempFile = $newDir;
        } elseif (file_exists($this->file->tempName)) {
            // ok. file exists.
        } else {
            $this->errors[] = [
                'line' => 0,
                'message' => Yii::t('csv/default', 'ERROR_FILE')
            ];
            return false;
        }

        $this->columns = $this->getFileHandler();

        //Проверка чтобы небыло атрибутов с таким же названием как и системные параметры
        $i = 1;
        //  CMS::dump($this->columns);die;

        foreach (AttributesProcessor::getImportExportData('eav_') as $key => $value) {
            if (mb_strpos($key, 'eav_') !== false) {
                $attributeName = str_replace('eav_', '', $key);
                if (in_array($attributeName, AttributesProcessor::skipNames)) {
                    $this->errors[] = [
                        'line' => 0,
                        'message' => Yii::t('csv/default', 'ERROR_COLUMN_ATTRIBUTE', [
                            'attribute' => $attributeName
                        ]),
                        'type' => Yii::t('csv/default', 'LIST', $this->type)
                    ];
                    return false;
                }
            }
            $i++;
        }

        foreach ($this->required as $column) {

            foreach ($this->columns as $listName => $col) {

                if (!in_array($column, $col[1])) {
                    $this->errors[] = [
                        'line' => 0,
                        'message' => Yii::t('csv/default', 'REQUIRE_COLUMN', [
                            'column' => $column,
                            'type' => Yii::t('csv/default', 'LIST', $listName)
                        ])
                    ];
                }
            }

        }

        return !$this->hasErrors();
    }


    /**
     * Get category id by path. If category not exits it will new one.
     * @param $path string Catalog/Shoes/Nike
     * @return integer category id
     */
    public function getCategoryByPath($path)
    {

        if (isset($this->categoriesPathCache[$path]))
            return $this->categoriesPathCache[$path];

        if ($this->rootCategory === null)
            $this->rootCategory = Category::findOne(1);

        $result = preg_split($this->subCategoryPattern, $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = array_map('stripcslashes', $result);


        // $test = $result;
        // krsort($test);

        $parent = $this->rootCategory;
        $level = 2; // Level 1 is only root
        /** @var \panix\engine\behaviors\nestedsets\NestedSetsBehavior $model */

        /*$leaf = array_pop($result);
        $tree = [];
        $branch = &$tree;
        foreach ($result as $name) {
            $branch[$name] = [];
            $branch = &$branch[$name];
        }
        $branch = $leaf;*/


        $pathName = '';
        $tree = [];
        foreach ($result as $key => $name) {
            $pathName .= '/' . trim($name);
            $tree[] = substr($pathName, 1);
        }


        foreach ($tree as $key => $name) {
            $object = explode('/', trim($name));

            $model = Category::find()->where(['path_hash' => md5(mb_strtolower($name))])->one();
            //$exist = Category::find()->where(['path_hash' => md5($name)])->one();
            //if ($exist) {
            //    $model = $exist;
            if (!$model) {
                $model = new Category;
                $model->name_ru = end($object);
                $model->slug = CMS::slug($model->name_ru);
                $model->appendTo($parent);
            }

            $parent = $model;
            $level++;

        }
        // Cache category id
        $this->categoriesPathCache[$path] = $model->id;
        if (isset($model)) {
            return $model->id;
        }

        return 1; // root category
    }

    /**
     * Apply column key to csv row.
     * @param $row array
     * @return array|boolean e.g array(key=>value)
     */
    public function prepareRow($row)
    {


        //$key = array_search('артикул', $this->testParams);
        //CMS::dump($key);
        $this->uniqueName = $row['бренд'] . $row['артикул'];

        //$model = $this->external->getObject(ExternalFinder::OBJECT_PRODUCT, $data['Наименование']);
        $this->model = $this->external->getObject(ExternalFinder::OBJECT_PRODUCT, $this->uniqueName);
        // $model = $query->one();
        $hasDeleted = false;

        if (!$this->model) {
            $this->isNew = true;
            $this->model = new Product;
            $this->totalProductCount++;
            if (isset($row['delete']) && $row['delete']) {
                $hasDeleted = true;
            }
        } else {
            if (isset($row['delete']) && $row['delete']) {
                $this->stats['deleted']++;
                $hasDeleted = true;
                $this->model->delete();
            }
        }

        if (!$hasDeleted) {
            $row = array_map('trim', $row);
            // $row = array_combine($this->csv_columns[1], $row);

            $row['created_at'] = time();
            $row['updated_at'] = time();
            $this->currentRow = $row;
            // return array_filter($row); // Remove empty keys and return result
            return $row; // Remove empty keys and return result

        } else {
            return false;
        }


    }


    /**
     * Find Currency
     * @param string $name
     * @return integer
     */
    public function getCurrencyIdByName($name)
    {
        if (isset($this->currencyCache[$name]))
            return $this->currencyCache[$name];

        $query = Currency::find()->where(['iso' => trim($name), 'switch' => 1]);
        /** @var Currency $model */
        $model = $query->one();

        if (!$model) {
            $this->warnings[] = [
                'line' => $this->line,
                'message' => Yii::t('csv/default', 'NO_FIND_CURRENCY', $name),
                'type' => Yii::t('csv/default', 'LIST', $this->type)
            ];

        }
        if ($model) {
            $this->currencyCache[$name] = $model->id;
            return $model->id;
        }
    }


    /**
     * Get product type by name. If type not exists - create new one.
     * @param $name
     * @return int
     */
    public function getTypeIdByName($name)
    {
        if (isset($this->productTypeCache[$name]))
            return $this->productTypeCache[$name];

        $model = ProductType::find()->where(['name' => $name])->one();

        if (!$model) {
            $model = new ProductType;
            $model->name = $name;
            $model->save();
        }

        $this->productTypeCache[$name] = $model->id;

        return $model->id;
    }

    protected function validateImage($image)
    {
        $imagesList = explode(';', $image);
        foreach ($imagesList as $i => $im) {

            $checkFile = mb_strtolower(pathinfo($im, PATHINFO_EXTENSION));

            if (!in_array($checkFile, self::$extension)) {
                $this->errors[] = [
                    'line' => $this->line,
                    'message' => Yii::t('csv/default', 'ERROR_IMAGE_EXTENSION', implode(', ', self::$extension)),
                ];
                return false;
            }

            if (empty($im)) {
                $this->errors[] = [
                    'line' => $this->line,
                    'message' => Yii::t('csv/default', 'ERROR_IMAGE'),
                    'type' => Yii::t('csv/default', 'LIST', $this->type)
                ];
                return false;
            }
        }
        return true;
    }

    /**
     * Get additional categories array from string separated by ";"
     * E.g. Video/cat1;Video/cat2
     * @param $str
     * @return array
     */
    public function getAdditionalCategories($str)
    {
        $result = [];
        $parts = explode(';', $str);
        foreach ($parts as $path) {
            $result[] = $this->getCategoryByPath(trim($path));
        }


        return $result;
    }


    /**
     * Find or create supplier
     * @param $name
     * @return integer
     */
    public function getSupplierIdByName($name)
    {
        if (isset($this->supplierCache[$name]))
            return $this->supplierCache[$name];


        $model = $this->external->getObject(ExternalFinder::OBJECT_SUPPLIER, trim($name), true);

        if (!$model) {
            $exist = Supplier::findOne(['name' => trim($name)]);
            if ($exist) {
                $model = $exist;
            } else {
                $model = new Supplier();
                $model->name = trim($name);
            }

            if ($model->save()) {
                $this->external->createExternalId(ExternalFinder::OBJECT_SUPPLIER, $model->id, $model->name);
            }
        }

        $this->supplierCache[$name] = $model->id;
        return $model->id;
    }


    /**
     * Find or create manufacturer
     * @param $name
     * @return integer
     */
    public function getManufacturerIdByName($name)
    {
        if (isset($this->manufacturerCache[$name]))
            return $this->manufacturerCache[$name];


        $model = $this->external->getObject(ExternalFinder::OBJECT_MANUFACTURER, trim($name), true);

        // $query = Manufacturer::find()
        //    ->where(['name' => trim($name)]);

        // $model = $query->one();
        if (!$model) {
            $exist = Manufacturer::findOne(['name_ru' => trim($name)]);
            if ($exist) {
                $model = $exist;
            } else {
                $model = new Manufacturer();
                $model->name_ru = trim($name);
                $model->slug = CMS::slug($model->name_ru);
            }
            if ($model->save()) {
                $this->external->createExternalId(ExternalFinder::OBJECT_MANUFACTURER, $model->id, $model->name_ru);
            }

        }

        $this->manufacturerCache[$name] = $model->id;
        return $model->id;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return !empty($this->errors);
    }


    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }


    /**
     * @return bool
     */
    public function hasWarnings()
    {
        return !empty($this->warnings);
    }


    /**
     * @return array
     */
    public function getWarnings()
    {
        return $this->warnings;
    }

    /**
     * Close file handler
     */
    public function __destruct()
    {
        if ($this->fileHandler !== null)
            fclose($this->fileHandler);
    }

    public function processImages()
    {
        if (isset($this->currentRow['фото']) && !empty($this->currentRow['фото'])) {

            if ($this->validateImage($this->currentRow['фото'])) {
                /** @var ImageBehavior $model */
                $imagesArray = explode(';', $this->currentRow['фото']);

                //$limit = Yii::$app->params['plan'][Yii::$app->user->planId]['product_upload_files'];
                //if ((count($imagesArray) > $limit) || $model->imagesCount > $limit) {
                //    $this->errors[] = [
                //        'line' => $this->line,
                //        'error' => Yii::t('shop/default', 'PRODUCT_LIMIT_IMAGE', count($imagesArray))
                //    ];
                // } else {
                foreach ($imagesArray as $n => $im) {
                    $imageName = $this->model->id . '_' . basename($im);
                    $externalFinderImage = $this->external->getObject(ExternalFinder::OBJECT_IMAGE, $imageName);

                    if (!$externalFinderImage) {
                        $images = $this->model->getImages();
                        if ($images) {
                            foreach ($images as $image) {
                                //$mi = $model->removeImage($image);
                                // if ($mi) {
                                $externalFinderImage2 = $this->external->getObject(ExternalFinder::OBJECT_IMAGE, $imageName, true, false, true);
                                if ($externalFinderImage2) {
                                    $mi = $this->model->removeImage($image);
                                    $externalFinderImage2->delete();
                                    $this->external->removeByPk(ExternalFinder::OBJECT_IMAGE, $image->id);
                                }
                                // }
                            }
                        }
                        $image = Image::create(trim($im));
                        if ($image) {

                            $result = $this->model->attachImage($image, true);

                            if ($this->deleteDownloadedImages) {
                                $image->deleteTempFile();
                            }
                            if ($result) {
                                /*$this->warnings[] = [
                                    'line' => $this->line,
                                    'message' => $imageName . ' ' . $result->id
                                ];*/
                                $this->external->createExternalId(ExternalFinder::OBJECT_IMAGE, $result->id, $imageName);
                            } else {
                                $this->errors[] = [
                                    'line' => $this->line,
                                    'message' => 'Ошибка изображения #0001',
                                    'type' => Yii::t('csv/default', 'LIST', $this->type)
                                ];
                            }
                        } else {
                            $this->warnings[] = [
                                'line' => $this->line,
                                'message' => 'Ошибка изображения: Не найдено! ' . trim($im),
                                'type' => Yii::t('csv/default', 'LIST', $this->type)
                            ];
                        }
                    }
                }
                //}
            }
        }
    }

    public function processCategories(int $main_category_id)
    {
        $categories = [$main_category_id];

        if (isset($data['доп. категории']) && !empty($data['доп. категории']))
            $categories = array_merge($categories, $this->getAdditionalCategories($data['доп. категории']));

        //if (!$newProduct) {
        //foreach ($model->categorization as $c)
        //    $categories[] = $c->category;
        $categories = array_unique($categories);
        //}
        $category = Category::findOne($main_category_id);

        if ($category) {
            $tes = $category->ancestors()->excludeRoot()->all();
            foreach ($tes as $cat) {
                $categories[] = $cat->id;
            }

        }

        // Update categories
        $this->model->setCategories($categories, $main_category_id);
    }

    public function processConfiguration(AttributesProcessor $attributes)
    {
        if ($this->model->use_configurations) {

            if (isset($this->currentRow['конфигурация'])) {
                $db = $this->model::getDb()->createCommand();
                if (!empty($this->currentRow['конфигурация']) && $this->currentRow['конфигурация'] != 'no') {
                    $configure_attribute_list = explode(';', $this->currentRow['конфигурация']);
                    $configureIds = [];
                    $db->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $this->model->id])->execute();
                    foreach ($configure_attribute_list as $configure_attribute) {
                        // $configure = Attribute::findOne(['name' => CMS::slug($configure_attribute, '_')]);
                        $configure = $attributes->getAttributeByName(CMS::slug($configure_attribute, '_'), $configure_attribute);

                        $db->insert('{{%shop__product_configurable_attributes}}', [
                            'product_id' => $this->model->id,
                            'attribute_id' => $configure->id
                        ])->execute();
                    }
                } else {
                    $db->update(Product::tableName(), [
                        'use_configurations' => 0,
                    ], ['id' => $this->model->id])->execute();
                    $db->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $this->model->id])->execute();
                }

            }
        }
    }

    public function processRelation()
    {
        if (isset($this->currentRow['связи'])) {
            if (!empty($this->currentRow['связи'])) {
                $relatedIds = explode(';', $this->currentRow['связи']);
                //Достаем только целые числа из массива.
                $filtered = array_filter($relatedIds, 'ctype_digit');

                $warnList = array_diff($relatedIds, $filtered);
                if ($warnList) {
                    $this->warnings[] = [
                        'line' => $this->line,
                        'message' => Yii::t('csv/default', 'FILTER_VALUE_NO_VALID', ['Связи', "<strong>" . implode('</strong>, <strong>', $warnList) . "</strong>"]),
                        'type' => Yii::t('csv/default', 'LIST', $this->type)
                    ];
                }

                if ($filtered)
                    $this->model->setRelatedProducts($filtered);
            }

        }
    }

    protected function productValidate()
    {
        if (!$this->model->validate()) {
            $errors = $this->model->getErrors();

            $error = array_shift($errors);
            $this->errors[] = [
                'line' => $this->line,
                'message' => $error[0],
                'type' => Yii::t('csv/default', 'LIST', $this->type)
            ];
            return false;
        } else {
            return true;
        }
    }

    public function setColumns($data)
    {
        $this->columns = $data;
    }
}