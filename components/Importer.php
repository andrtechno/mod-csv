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
use yii\db\Query;
use yii\helpers\ArrayHelper;
use yii\queue\Queue;
use yii\web\UploadedFile;

/**
 * Import products from csv format
 * Images must be located at ./uploads/importImages
 */
class Importer extends Component
{

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
    public $required = ['Наименование', 'Категория', 'Цена', 'Тип', 'Бренд', 'Артикул'];

    public $totalProductCount = 0;

    /**
     * @var ExternalFinder
     */
    public $external;

    public function __construct(array $config = [])
    {
       // $this->external = new ExternalFinder('{{%csv}}');
        $class = Yii::$app->getModule('csv')->externalClass;
        $this->external = new $class('{{%csv}}');
        //externalClass
        parent::__construct($config);
    }

    public function getFileHandler()
    {
        $config = Yii::$app->settings->get('csv');
        $indentRow = (isset($config->indent_row)) ? $config->indent_row : 1;
        $indentColumn = (isset($config->indent_column)) ? $config->indent_column : 1;
        $ignoreColumns = (isset($config->ignore_columns)) ? explode(',', $config->ignore_columns) : [];
        //  $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->newfile);


        if ($this->file->extension == 'xlsx') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        } elseif ($this->file->extension == 'xls') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
        } elseif ($this->file->extension == 'csv') {
            $reader = new \PhpOffice\PhpSpreadsheet\Reader\Csv();
        } else {
            die('FATAL ERROR: xlsx, xls');
        }

        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $spreadsheet = $reader->load($this->newfile);


        $worksheet = $spreadsheet->getActiveSheet();


        //$props = $spreadsheet->getProperties();

        $rows = [];
        $cellsHeaders = [];
        foreach ($worksheet->getRowIterator($indentRow, 1) as $k => $row) {
            $cellIterator2 = $row->getCellIterator(Helper::num2alpha($indentColumn));
            $cellIterator2->setIterateOnlyExistingCells(false); // This loops through all cells,
            foreach ($cellIterator2 as $column => $cell2) {
                $value = trim($cell2->getValue());
                if (!in_array(mb_strtolower($column), $ignoreColumns)) {
                    if (!empty($value)) {
                        $cellsHeaders[$column] = $value;
                    }
                }
            }

        }

        foreach ($worksheet->getRowIterator($indentRow + 1) as $k2 => $row) {

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
            //remove empty rows
            $empty = 0;
            foreach ($cells as $c) {
                if (empty($c))
                    $empty++;
            }
            if ($empty != count($cells)) {
                $rows[$k2] = $cells;
            }
        }

        return [$cellsHeaders, $rows];

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
            $this->errors[] = ['line' => 0, 'error' => Yii::t('csv/default', 'ERROR_FILE')];
            return false;
        }

        $this->columns = $this->getFileHandler();

        //Проверка чтобы небыло атрибутов с таким же названием как и системные параметры
        $i = 1;

        foreach (AttributesProcessor::getImportExportData('eav_') as $key => $value) {
            if (mb_strpos($key, 'eav_') !== false) {
                $attributeName = str_replace('eav_', '', $key);
                if (in_array($attributeName, AttributesProcessor::skipNames)) {
                    $this->errors[] = [
                        'line' => 0,
                        'error' => Yii::t('csv/default', 'ERROR_COLUMN_ATTRIBUTE', [
                            'attribute' => $attributeName
                        ])
                    ];
                    return false;
                }
            }
            $i++;
        }

        foreach ($this->required as $column) {
            if (!in_array($column, $this->columns[0]))
                $this->errors[] = [
                    'line' => 0,
                    'error' => Yii::t('csv/default', 'REQUIRE_COLUMN', ['column' => $column])
                ];
        }

        return !$this->hasErrors();
    }

    public $skipRows = []; //@todo: need test
    public $language = 'ru';
    /**
     * Here we go
     */
    public function import()
    {
        // Process lines
        $this->line = 1;

        $counter = 0;
        $queueList = [];

        //Remove empty rows
        $columns2 = array_filter($this->columns[1], function ($value) {
            foreach ($value as $row) {
                if (!is_null($row) && !empty($row)) {
                    return $row;
                }
            }
            return [];
        });
        $columns = $this->columns[1];
//CMS::dump($columns);die;
        foreach ($columns as $columnIndex => $row) {
            $this->line = $columnIndex;
            if (isset($row['Наименование'], $row['Цена'], $row['Категория'], $row['Тип'])) {

                $row = array_filter($row, function ($value, $key) {
                    if (in_array($key, $this->required)) {
                        if (empty($value)) {
                            $this->errors[] = [
                                'line' => $this->line,
                                'error' => Yii::t('csv/default', 'REQUIRE_COLUMN_EMPTY', ['column' => $key])
                            ];
                            $this->skipRows[] = $this->line;
                        }
                    }
                    return [$key => $value];
                }, ARRAY_FILTER_USE_BOTH);

                if (!in_array($columnIndex, $this->skipRows)) {//if (!$this->errors) {
                    $row = $this->prepareRow($row);
                    if ($counter <= self::QUEUE_ROW) {

                        $this->importRow($row);
                    } else {
                        $queueList[$this->line] = $row;
                    }
                } else {
                    // echo 'error:1-';
                    echo $this->line;
                    die;
                }

            } else {
                // echo 'error:2-';
                echo $this->line;
                die;
            }

            $counter++;
        }
        //  CMS::dump($queueList);
        // echo count($queueList);die;
        if ($queueList) {
            Yii::$app->session->addFlash('success', 'В очередь добавлено: <strong>' . count($queueList) . '</strong> товара');
            $list = array_chunk($queueList, self::QUEUE_ROW, true);
            /** @var Queue $q */
            $q = Yii::$app->queue;
            foreach ($list as $index => $items) {
                $q->priority($index)->push(new QueueImport([
                    'rows' => $items,
                    'language' => $this->language,
                    'remove_images' => $this->deleteDownloadedImages
                ]));
            }
        }
    }

    /**
     * Create/update product from key=>value array
     * @param $data array of product attributes
     */
    public function importRow($data)
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
        $full_name = $data['Бренд'] . $data['Артикул'];

        //$brand = $data['Бренд'];
        //$sku = $data['Артикул'];
        // }

        //$model = $this->external->getObject(ExternalFinder::OBJECT_PRODUCT, $data['Наименование']);
        $model = $this->external->getObject(Yii::$app->getModule('csv')->externalClass::OBJECT_PRODUCT, $full_name);
        // $model = $query->one();
        $hasDeleted = false;

        if (!$model) {
            $newProduct = true;
            $model = new Product;
            $this->totalProductCount++;
            if (isset($data['deleted']) && $data['deleted']) {
                $hasDeleted = true;
				//echo $full_name;
				//echo \panix\engine\CMS::hash($full_name, true);
				//echo '22';
				//die;
            }
			
        } else {
            if (isset($data['deleted']) && $data['deleted']) {
                $this->stats['deleted']++;
                $hasDeleted = true;
                $model->delete();
			  // echo '11';
			  // die;
            }
			
        }

        if (!$hasDeleted) {

            if (isset($data['Категория']) || !empty($data['Категория'])) {
                $category_id = $this->getCategoryByPath($data['Категория']);
            }
            // Process product type
            $config = Yii::$app->settings->get('csv');

            $model->type_id = $this->getTypeIdByName($data['Тип']);

            $model->main_category_id = $category_id;

            if (isset($data['switch']) && !empty($data['switch'])) {
                $model->switch = $data['switch'];
            } else {
                $model->switch = 1;
            }


            //Если товар используеться в чейто конфигурации то скрываем
            if (!$model->isNewRecord) {
                $query = new Query();
                $query->select('*')->from('{{%shop__product_configurations}}')
                    ->where(['configurable_id' => $model->id]);
                $configurable = $query->one();
                if ($configurable) {
                    //  CMS::dump($configurable);die;
                    $model->switch = 0;
                }
            }

            if (isset($data['Цена']) && !empty($data['Цена'])) {
                $model->price = $data['Цена'];
            }

            if (isset($data['Наименование']) && !empty($data['Наименование'])) {
                $model->{"name_".$this->language} = $data['Наименование'];
            }


            if (isset($data['Цена закупки']) && !empty($data['Цена закупки']))
                $model->price_purchase = $data['Цена закупки'];

            if (isset($data['unit']) && !empty($data['unit']) && array_search(trim($data['unit']), $model->getUnits())) {
                $model->unit = array_search(trim($data['unit']), $model->getUnits());
            } else {
                $model->unit = 1;
            }


            // Manufacturer
            if (isset($data['Бренд']) && !empty($data['Бренд']))
                $model->manufacturer_id = $this->getManufacturerIdByName($data['Бренд']);

            // Supplier
            if (isset($data['Поставщик']) && !empty($data['Поставщик']))
                $model->supplier_id = $this->getSupplierIdByName($data['Поставщик']);

            if (isset($data['Артикул']) && !empty($data['Артикул']))
                $model->sku = $data['Артикул'];

            if (isset($data['Описание']) && !empty($data['Описание']))
                $model->{"full_description_".$this->language} = $data['Описание'];

            if (isset($data['Наличие']) && !empty($data['Наличие']))
                $model->availability = (is_numeric($data['Наличие'])) ? $data['Наличие'] : 1;


            if (isset($data['Конфигурация']) && !empty($data['Конфигурация'])) {
                $model->use_configurations = 1;
            }


            // Currency
            if (isset($data['Валюта']) && !empty($data['Валюта']))
                $model->currency_id = $this->getCurrencyIdByName($data['Валюта']);

            if (isset($data['Скидка'])) {
                $model->discount = (!empty($data['Скидка'])) ? $data['Скидка'] : NULL;
            } else {
                $model->discount = NULL;
            }


            if (isset($data['Лейблы'])) {
                $model->label = (!empty($data['Лейблы'])) ? str_replace(';', ',', $data['Лейблы']) : NULL;
            } else {
                $model->label = NULL;
            }


            // Update product variables and eav attributes.
            $attributes = new AttributesProcessor($model, $data);

            if ($model->validate()) {

                $categories = [$category_id];

                if (isset($data['Доп. Категории']) && !empty($data['Доп. Категории']))
                    $categories = array_merge($categories, $this->getAdditionalCategories($data['Доп. Категории']));

                //if (!$newProduct) {
                //foreach ($model->categorization as $c)
                //    $categories[] = $c->category;
                $categories = array_unique($categories);
                //}


                $this->stats[(($model->isNewRecord) ? 'create' : 'update')]++;

                if (isset($data['Связи']) && !empty($data['Связи'])) {
                    $this->processRelation($model, $data['Связи']);
                }

                // Save product
                $model->save();
                if ($model->use_configurations) {
                    if (isset($data['Конфигурация'])) {

                        $db = $model::getDb()->createCommand();
                        if (!empty($data['Конфигурация']) && $data['Конфигурация'] != 'no') {
                            $configure_attribute_list = explode(';', $data['Конфигурация']);
                            $db->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $model->id])->execute();
                            $db->delete('{{%shop__product_configurations}}', ['product_id' => $model->id])->execute();

                            foreach ($configure_attribute_list as $configure_attribute_item) {
                                list($config_attribute, $cofigure_items) = explode('=', $configure_attribute_item);
                                $items = explode(',', $cofigure_items);


                                // foreach ($configure_attribute_list as $configure_attribute) {
                                // $configure = Attribute::findOne(['name' => CMS::slug($configure_attribute, '_')]);
                                $configure = $attributes->getAttributeByName(CMS::slug($config_attribute, '_'), $config_attribute);

                                $db->insert('{{%shop__product_configurable_attributes}}', [
                                    'product_id' => $model->id,
                                    'attribute_id' => $configure->id
                                ])->execute();
                                // }

                                foreach ($items as $item) {
                                    $query = new Query();
                                    $query->select('id,manufacturer_id')
                                        ->from('{{%shop__product}}')
                                        ->where(['sku' => trim($item), 'manufacturer_id' => $model->manufacturer_id]);
                                    $product = $query->one(); //items for else many duplicate sku
                                    if ($product) {
                                        $db->insert('{{%shop__product_configurations}}', [
                                            'product_id' => $model->id,
                                            'configurable_id' => $product['id']
                                        ])->execute();

                                        $db->update(Product::tableName(), ['switch' => 0], ['id' => $product['id']])->execute();
                                    }
                                }
                            }

                        } else {
                            $db->update(Product::tableName(), [
                                'use_configurations' => 0,
                            ], ['id' => $model->id])->execute();
                            $db->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $model->id])->execute();


                            $db->delete('{{%shop__product_configurations}}', [
                                'product_id' => $model->id,
                            ])->execute();

                        }

                    }

                }

                // Create product external id
                if ($newProduct === true) {
                    $this->external->createExternalId(Yii::$app->getModule('csv')->externalClass::OBJECT_PRODUCT, $model->id, $full_name);
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

                if (isset($data['Фото']) && !empty($data['Фото'])) {

                    if ($this->validateImage($data['Фото'])) {
                        /** @var ImageBehavior $model */
                        $imagesArray = explode(';', $data['Фото']);

                        //$limit = Yii::$app->params['plan'][Yii::$app->user->planId]['product_upload_files'];
                        //if ((count($imagesArray) > $limit) || $model->imagesCount > $limit) {
                        //    $this->errors[] = [
                        //        'line' => $this->line,
                        //        'error' => Yii::t('shop/default', 'PRODUCT_LIMIT_IMAGE', count($imagesArray))
                        //    ];
                        // } else {
                        foreach ($imagesArray as $n => $im) {
                            $imageName = $model->id . '_' . basename($im);
                            $externalFinderImage = $this->external->getObject(Yii::$app->getModule('csv')->externalClass::OBJECT_IMAGE, $imageName);
                            if (!$externalFinderImage) {
                                $images = $model->getImages();
                                if ($images) {
                                    foreach ($images as $image) {
                                        //$mi = $model->removeImage($image);
                                        // if ($mi) {
                                        $externalFinderImage2 = $this->external->getObject(Yii::$app->getModule('csv')->externalClass::OBJECT_IMAGE, $imageName, true, false, true);
                                        if ($externalFinderImage2) {
                                            $mi = $model->removeImage($image);
                                            $externalFinderImage2->delete();
                                            $this->external->removeByPk(Yii::$app->getModule('csv')->externalClass::OBJECT_IMAGE, $image->id);
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
                                        $this->external->createExternalId(Yii::$app->getModule('csv')->externalClass::OBJECT_IMAGE, $result->id, $imageName);
                                    } else {
                                        $this->errors[] = [
                                            'line' => $this->line,
                                            'error' => 'Ошибка изображения #0001'
                                        ];
                                    }
                                } else {
                                    $this->warnings[] = [
                                        'line' => $this->line,
                                        'error' => 'Ошибка изображения: Не найдено! ' . trim($im)
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
                    'error' => $error[0]
                ];
            }
        }
    }

    public function processRelation($model, $data)
    {
        $ids = [];
        if ($data != 'no') {
            $relatedIds = explode(';', $data);


            foreach ($relatedIds as $item) {
                $query = new Query();
                $query->select('id')
                    ->from('{{%shop__product}}')
                    ->where(['sku' => trim($item)]);
                $products = $query->all(); //items for else many duplicate sku

                foreach ($products as $product) {
                    $ids[] = $product['id'];
                }

            }

        }
        $model->setRelatedProducts($ids);

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
                    'error' => Yii::t('csv/default', 'ERROR_IMAGE')
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


        $model = $this->external->getObject(Yii::$app->getModule('csv')->externalClass::OBJECT_SUPPLIER, trim($name), true);

        if (!$model) {
            $exist = Supplier::findOne(['name' => trim($name)]);
            if ($exist) {
                $model = $exist;
            } else {
                $model = new Supplier();
                $model->name = trim($name);
            }

            if ($model->save()) {
                $this->external->createExternalId(Yii::$app->getModule('csv')->externalClass::OBJECT_SUPPLIER, $model->id, $model->name);
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


        $model = $this->external->getObject(Yii::$app->getModule('csv')->externalClass::OBJECT_MANUFACTURER, trim($name), true);

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
                $this->external->createExternalId(Yii::$app->getModule('csv')->externalClass::OBJECT_MANUFACTURER, $model->id, $model->name_ru);
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
            $this->warnings[] = ['line' => $this->line, 'error' => Yii::t('csv/default', 'NO_FIND_CURRENCY', $name)];

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

        //return array_filter($row); // Remove empty keys and return result
        return $row; // Remove empty keys and return result
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
