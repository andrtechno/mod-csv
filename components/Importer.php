<?php

namespace panix\mod\csv\components;


use panix\mod\shop\components\ExternalFinder;
use panix\mod\shop\models\Supplier;
use PhpOffice\PhpSpreadsheet\Document\Properties;
use panix\mod\csv\components\AttributesProcessor;
use panix\mod\csv\components\Image;
use Yii;
use yii\base\Component;
use panix\engine\CMS;
use panix\mod\shop\models\Manufacturer;
use panix\mod\shop\models\ProductType;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\Product;
use panix\mod\images\behaviors\ImageBehavior;
use panix\mod\shop\models\Currency;
use yii\base\Exception;
use yii\helpers\ArrayHelper;
use yii\queue\Queue;
use yii\web\UploadedFile;

/**
 * Import products from csv format
 * Images must be located at ./uploads/importImages
 */
class Importer extends Component
{
    public $skipRows = []; //@todo: need test
    /**
     * @var string column delimiter
     */
    public $delimiter = ",";

    /**
     * @var string
     */
    public $enclosure = '"';

    /**
     * @var UploadedFile path to file
     */
    public $file;


    public $newfile;

    /**
     * @var string encoding.
     */
    public $encoding;

    /**
     * @var string
     */
    public $subCategoryPattern = '/\\/((?:[^\\\\\/]|\\\\.)*)/';

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
     * @var array
     */
    protected $categoriesPathCache = [];

    /**
     * @var array
     */
    protected $productTypeCache = [];

    /**
     * @var array
     */
    protected $manufacturerCache = [];

    /**
     * @var array
     */
    protected $supplierCache = [];

    /**
     * @var array
     */
    protected $currencyCache = [];

    /**
     * @var int
     */
    public $line = 1;
    public $type;
    /**
     * @var array
     */
    protected $errors = [];
    protected $warnings = [];
    const QUEUE_ROW = 25;
    /**
     * @var array
     */
    public $stats = [
        'create' => 0,
        'update' => 0,
        'deleted' => 0
    ];
    public static $extension = ['jpg', 'jpeg'];
    public $required = ['наименование', 'категория', 'цена'];

    public $totalProductCount = 0;

    /**
     * @var ExternalFinder
     */
    public $external;

    public function __construct(array $config = [])
    {
        $this->external = new ExternalFinder('{{%csv}}');
        parent::__construct($config);
    }

    public function getFileHandler()
    {
        $config = Yii::$app->settings->get('csv');
        $indentRow = (isset($config->indent_row)) ? $config->indent_row : 1;
        $indentColumn = (isset($config->indent_column)) ? $config->indent_column : 1;
        $ignoreColumns = (isset($config->ignore_columns)) ? explode(',', $config->ignore_columns) : [];
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->newfile);

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
                                preg_match('/(IMAGE).*[\'"](https?:\/\/?.*)[\'"]/iu', $cell->getValue(), $match);
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

            $columns[$title] = array_filter($result[$title], function ($value) {
                if ($value) {
                    foreach ($value as $row) {
                        if (!is_null($row) && !empty($row)) {
                            return $row;
                        }
                    }
                }
                return [];
            });
        }


        // CMS::dump($columns);die;
        return $columns;


    }

    /**
     * @return bool validate csv file
     */
    public function validate()
    {

        $this->totalProductCount = Product::find()->count();
        // Check file exists and readable
        if (is_uploaded_file($this->file->tempName)) {

            $newDir = Yii::getAlias('@runtime') . '/tmp.' . $this->file->extension;
            move_uploaded_file($this->file->tempName, $newDir);
            $this->newfile = $newDir;
        } elseif (file_exists($this->file->tempName)) {
            // ok. file exists.
        } else {
            $this->errors[] = [
                'line' => 0,
                'error' => Yii::t('csv/default', 'ERROR_FILE')
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
                        'error' => Yii::t('csv/default', 'ERROR_COLUMN_ATTRIBUTE', [
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
                        'error' => Yii::t('csv/default', 'REQUIRE_COLUMN', [
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
                                    'error' => Yii::t('csv/default', 'REQUIRE_COLUMN_EMPTY', ['column' => $key]),
                                    'type' => Yii::t('csv/default', 'LIST', $this->type)
                                ];
                                $this->skipRows[] = $this->line;
                            }
                        }
                        return [$key => $value];
                    }, ARRAY_FILTER_USE_BOTH);

                    if (!in_array($columnIndex, $this->skipRows)) {//if (!$this->errors) {
                        $row = $this->prepareRow($row);
                        if ($counter <= self::QUEUE_ROW) {
                            $this->importRow($row, $type);
                        } else {
                            $queueList[$this->line] = $row;
                        }
                    }

                }

                $counter++;
            }

            if ($queueList) {
                Yii::$app->session->addFlash('success', Yii::t('csv/default', 'QUEUE_ADD', [
                    'type' => $type,
                    'count' => count($queueList)
                ]));
                $list = array_chunk($queueList, self::QUEUE_ROW, true);
                /** @var Queue $q */
                $q = Yii::$app->queue;
                foreach ($list as $index => $items) {
                    $q->priority($index)->push(new QueueImport(['rows' => $items, 'type' => $this->type]));
                }
            }
            //  }


        }


    }

    /**
     * Create/update product from key=>value array
     * @param $data array of product attributes
     */
    public function importRow($data, $type)
    {

        $category_id = 1;


        $newProduct = false;

        // $query = Product::find();

        // Search product by name, category
        // or create new one
        //if (isset($data['sku']) && !empty($data['sku']) && $data['sku'] != '') {
        //   $query->where([Product::tableName() . '.sku' => $data['sku']]);
        // } else {
        //$query->where(['name' => $data['Наименование']]);
        // }

        //  if(true){
        $full_name = $data['бренд'] . $data['артикул'];

        //$brand = $data['Бренд'];
        //$sku = $data['Артикул'];
        // }

        //$model = $this->external->getObject(ExternalFinder::OBJECT_PRODUCT, $data['Наименование']);
        $model = $this->external->getObject(ExternalFinder::OBJECT_PRODUCT, $full_name);
        // $model = $query->one();
        $hasDeleted = false;

        if (!$model) {
            $newProduct = true;
            $model = new Product;
            $this->totalProductCount++;
            if (isset($data['deleted']) && $data['deleted']) {
                $hasDeleted = true;
            }
        } else {
            if (isset($data['deleted']) && $data['deleted']) {
                $this->stats['deleted']++;
                $hasDeleted = true;
                $model->delete();
            }
        }

        if (!$hasDeleted) {
            if (isset($data['категория']) || !empty($data['категория'])) {
                $category_id = $this->getCategoryByPath($data['категория']);
            }
            // Process product type
            $config = Yii::$app->settings->get('csv');

            $model->type_id = $this->getTypeIdByName($type);

            $model->main_category_id = $category_id;

            if (isset($data['switch']) && !empty($data['switch'])) {
                $model->switch = $data['switch'];
            } else {
                $model->switch = 1;
            }
            if (isset($data['цена']) && !empty($data['цена'])) {
                $model->price = $data['цена'];
            }

            if (isset($data['наименование']) && !empty($data['наименование'])) {
                $model->name = $data['наименование'];
            }


            if (isset($data['цена закупки']) && !empty($data['цена закупки']))
                $model->price_purchase = $data['цена закупки'];

            if (isset($data['unit']) && !empty($data['unit']) && array_search(trim($data['unit']), $model->getUnits())) {
                $model->unit = array_search(trim($data['unit']), $model->getUnits());
            } else {
                $model->unit = 1;
            }


            // Manufacturer
            if (isset($data['бренд']) && !empty($data['бренд']))
                $model->manufacturer_id = $this->getManufacturerIdByName($data['бренд']);

            // Supplier
            if (isset($data['поставщик']) && !empty($data['поставщик']))
                $model->supplier_id = $this->getSupplierIdByName($data['поставщик']);

            if (isset($data['артикул']) && !empty($data['артикул']))
                $model->sku = $data['артикул'];

            if (isset($data['описание']) && !empty($data['описание']))
                $model->full_description = $data['описание'];

            if (isset($data['наличие']) && !empty($data['наличие']))
                $model->availability = (is_numeric($data['наличие'])) ? $data['наличие'] : 1;


            if (isset($data['конфигурация']) && !empty($data['конфигурация'])) {
                $model->use_configurations = 1;
            }


            // Currency
            if (isset($data['валюта']) && !empty($data['валюта']))
                $model->currency_id = $this->getCurrencyIdByName($data['валюта']);

            if (isset($data['скидка'])){
                $model->discount = (!empty($data['скидка'])) ? $data['скидка'] : NULL;
            }else{
                $model->discount = NULL;
            }


            // Update product variables and eav attributes.
            $attributes = new AttributesProcessor($model, $data);

            if ($model->validate()) {

                $categories = [$category_id];

                if (isset($data['доп. категории']) && !empty($data['доп. категории']))
                    $categories = array_merge($categories, $this->getAdditionalCategories($data['доп. категории']));

                //if (!$newProduct) {
                //foreach ($model->categorization as $c)
                //    $categories[] = $c->category;
                $categories = array_unique($categories);
                //}


                $this->stats[(($model->isNewRecord) ? 'create' : 'update')]++;

                // Save product
                $model->save();
                //  var_dump($model->use_configurations);die;
                if ($model->use_configurations) {
                    // if(!isset($data['Конфигурация'])){
                    //     die('err'.$this->line);
                    //  }
                    $db = $model::getDb()->createCommand();
                    $configure_attribute_list = explode(';', $data['конфигурация']);
                    $configureIds = [];
                    $db->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $model->id])->execute();
                    foreach ($configure_attribute_list as $configure_attribute) {

                        // $configure = Attribute::findOne(['name' => CMS::slug($configure_attribute, '_')]);
                        $configure = $attributes->getAttributeByName(CMS::slug($configure_attribute, '_'), $configure_attribute);
                        // if (!$configure) {

                        // }

                        $db->insert('{{%shop__product_configurable_attributes}}', [
                            'product_id' => $model->id,
                            'attribute_id' => $configure->id
                        ])->execute();
                    }


                }

                // Create product external id
                if ($newProduct === true) {
                    $this->external->createExternalId(ExternalFinder::OBJECT_PRODUCT, $model->id, $full_name);
                }


                // Update EAV data
                $attributes->save();


                $category = Category::findOne($category_id);

                if ($category) {
                    $tes = $category->ancestors()->excludeRoot()->all();
                    foreach ($tes as $cat) {
                        $categories[] = $cat->id;
                    }

                }

                // Update categories
                $model->setCategories($categories, $category_id);

                if (isset($data['фото']) && !empty($data['фото'])) {

                    if ($this->validateImage($data['фото'])) {
                        /** @var ImageBehavior $model */
                        $imagesArray = explode(';', $data['фото']);

                        //$limit = Yii::$app->params['plan'][Yii::$app->user->planId]['product_upload_files'];
                        //if ((count($imagesArray) > $limit) || $model->imagesCount > $limit) {
                        //    $this->errors[] = [
                        //        'line' => $this->line,
                        //        'error' => Yii::t('shop/default', 'PRODUCT_LIMIT_IMAGE', count($imagesArray))
                        //    ];
                        // } else {
                        foreach ($imagesArray as $n => $im) {
                            $imageName = $model->id . '_' . basename($im);
                            $externalFinderImage = $this->external->getObject(ExternalFinder::OBJECT_IMAGE, $imageName);

                            if (!$externalFinderImage) {
                                $images = $model->getImages();
                                if ($images) {
                                    foreach ($images as $image) {
                                        //$mi = $model->removeImage($image);
                                        // if ($mi) {
                                        $externalFinderImage2 = $this->external->getObject(ExternalFinder::OBJECT_IMAGE, $imageName, true, false, true);
                                        if ($externalFinderImage2) {
                                            $mi = $model->removeImage($image);
                                            $externalFinderImage2->delete();
                                            $this->external->removeByPk(ExternalFinder::OBJECT_IMAGE, $image->id);
                                        }
                                        // }
                                    }
                                }
                                $image = Image::create(trim($im));
                                if ($image) {

                                    $result = $model->attachImage($image, true);

                                    if ($this->deleteDownloadedImages) {
                                        $image->deleteTempFile();
                                    }
                                    if ($result) {
                                        /*$this->warnings[] = [
                                            'line' => $this->line,
                                            'error' => $imageName . ' ' . $result->id
                                        ];*/
                                        $this->external->createExternalId(ExternalFinder::OBJECT_IMAGE, $result->id, $imageName);
                                    } else {
                                        $this->errors[] = [
                                            'line' => $this->line,
                                            'error' => 'Ошибка изображения #0001',
                                            'type' => Yii::t('csv/default', 'LIST', $type)
                                        ];
                                    }
                                } else {
                                    $this->warnings[] = [
                                        'line' => $this->line,
                                        'error' => 'Ошибка изображения: Не найдено! ' . trim($im),
                                        'type' => Yii::t('csv/default', 'LIST', $type)
                                    ];
                                }
                            }
                        }
                        //}
                    }
                }

            } else {
                $errors = $model->getErrors();

                $error = array_shift($errors);
                $this->errors[] = [
                    'line' => $this->line,
                    'error' => $error[0],
                    'type' => Yii::t('csv/default', 'LIST', $type)
                ];
            }
        }
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

    private function validateImage($image)
    {
        $imagesList = explode(';', $image);
        foreach ($imagesList as $i => $im) {

            $checkFile = mb_strtolower(pathinfo($im, PATHINFO_EXTENSION));

            if (!in_array($checkFile, self::$extension)) {
                $this->errors[] = [
                    'line' => $this->line,
                    'error' => Yii::t('csv/default', 'ERROR_IMAGE_EXTENSION', implode(', ', self::$extension))
                ];
                return false;
            }

            if (empty($im)) {
                $this->errors[] = [
                    'line' => $this->line,
                    'error' => Yii::t('csv/default', 'ERROR_IMAGE'),
                    'type' => Yii::t('csv/default', 'LIST', $this->type)
                ];
                return false;
            }
        }
        return true;
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
     * Find Currency
     * @param string $name
     * @return integer
     * @throws Exception
     */
    public function getCurrencyIdByName($name)
    {
        if (isset($this->currencyCache[$name]))
            return $this->currencyCache[$name];

        $query = Currency::find()->where(['iso' => trim($name)]);
        /** @var Currency $model */
        $model = $query->one();

        if (!$model) {
            $this->warnings[] = [
                'line' => $this->line,
                'error' => Yii::t('csv/default', 'NO_FIND_CURRENCY', $name),
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

    /**
     * Get category id by path. If category not exits it will new one.
     * @param $path string Catalog/Shoes/Nike
     * @return integer category id
     */
    protected function getCategoryByPath($path)
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

    private function test2($tree)
    {
        $data = [];
        $test = '';
        if (is_array($tree)) {
            foreach ($tree as $key => $name) {
                $data[$key] = $this->test2($name);
            }
        } else {
            $data[] = $tree;
        }

        return $data;
    }


    /**
     * Apply column key to csv row.
     * @param $row array
     * @return array e.g array(key=>value)
     */
    public function prepareRow($row)
    {
        $row = array_map('trim', $row);
        // $row = array_combine($this->csv_columns[1], $row);

        $row['created_at'] = time();
        $row['updated_at'] = time();//date('Y-m-d H:i:s');

        return array_filter($row); // Remove empty keys and return result
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

}
