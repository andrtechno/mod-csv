<?php

namespace panix\mod\csv\components;


use Yii;
use yii\base\Component;
use panix\engine\CMS;
use panix\mod\shop\models\Manufacturer;
use panix\mod\shop\models\ProductType;
use panix\mod\shop\models\translate\CategoryTranslate;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\Product;
use panix\mod\images\behaviors\ImageBehavior;
use panix\mod\shop\models\Currency;
use panix\mod\shop\models\Supplier;

/**
 * Import products from csv format
 * Images must be located at ./uploads/importImages
 */
class CsvImporter extends Component
{

    /**
     * @var string column delimiter
     */
    public $delimiter = ";";

    /**
     * @var int
     */
    public $maxRowLength = 10000;

    /**
     * @var string
     */
    public $enclosure = '"';

    /**
     * @var string path to file
     */
    public $file;

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
    protected $csv_columns = [];

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
    protected $line = 1;

    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var array
     */
    public $stats = [
        'create' => 0,
        'update' => 0,
        'deleted' => 0
    ];
    public static $extension = ['jpg', 'jpeg'];
    public $required = ['category', 'price', 'sku', 'type'];


    /**
     * @return bool validate csv file
     */
    public function validate()
    {


        // Check file exists and readable
        if (is_uploaded_file($this->file)) {
            $newDir = Yii::getAlias('@runtime') . '/tmp.csv';
            move_uploaded_file($this->file, $newDir);
            $this->file = $newDir;
        } elseif (file_exists($this->file)) {
            // ok. file exists.
        } else {
            $this->errors[] = ['line' => 0, 'error' => Yii::t('csv/default', 'ERROR_FILE')];
            return false;
        }

        $file = $this->getFileHandler();

        // Read first line to get attributes
        $line = fgets($file);
        $this->csv_columns = str_getcsv($line, $this->delimiter, $this->enclosure);

        foreach ($this->required as $column) { //'name', 'type', 
            if (!in_array($column, $this->csv_columns))
                $this->errors[] = [
                    'line' => 0,
                    'error' => Yii::t('csv/default', 'REQUIRE_COLUMN', ['column' => $column])
                ];
        }

        return !$this->hasErrors();
    }

    /**
     * Here we go
     */
    public function import()
    {
        $file = $this->getFileHandler();
        fgets($file); // Skip first
        // Process lines
        $this->line = 1;

        while (($row = fgetcsv($file, $this->maxRowLength, $this->delimiter, $this->enclosure)) !== false) {
            $row = $this->prepareRow($row);
            $this->line++;
            $this->importRow($row);
        }
    }

    /**
     * Create/update product from key=>value array
     * @param $data array of product attributes
     */
    protected function importRow($data)
    {

        if (!isset($data['category']) || empty($data['category']))
            $data['category'] = 'root';

        $newProduct = false;

        $category_id = $this->getCategoryByPath($data['category']);

        $query = Product::find();

        // Search product by name, category
        // or create new one
        //if (isset($data['sku']) && !empty($data['sku']) && $data['sku'] != '') {
        //   $query->where([Product::tableName() . '.sku' => $data['sku']]);
        // } else {
        $query->joinWith('translations as translate');
        $query->where(['translate.name' => $data['name']]); //$cr->compare('translate.name', $data['name']);
        // }

        $query->applyCategories($category_id);

        $model = $query->one();

        $hasDeleted = false;
        if (!$model) {
            $newProduct = true;
            $model = new Product;
            $this->stats['create']++;
        } else {
            $this->stats['update']++;
            if (isset($data['deleted']) && $data['deleted']) {
                $this->stats['deleted']++;
                $hasDeleted = true;
                $model->delete();
                $this->externalFinder->removeObject(ExternalFinder::OBJECT_PRODUCT, $data['fleshka_id']);
            }

        }
        if (!$hasDeleted) {
        // Process product type
        $config = Yii::$app->settings->get('csv');
        $model->type_id = $this->getTypeIdByName($data['type']);
        $model->main_category_id = $category_id;
        $model->switch = isset($data['switch']) ? $data['switch'] : 1;

        if (isset($data['unit']) && !empty($data['unit']) && array_search(trim($data['unit']), $model->getUnits())) {
            $model->unit = array_search(trim($data['unit']), $model->getUnits());
        }else{
            $model->unit=1;
        }

        // $model->price = $pricesList[0];

        // Manufacturer
        if (isset($data['manufacturer']) && !empty($data['manufacturer']))
            $model->manufacturer_id = $this->getManufacturerIdByName($data['manufacturer']);

        // Supplier
        if (isset($data['supplier']) && !empty($data['supplier']))
            $model->supplier_id = $this->getSupplierIdByName($data['supplier']);

        // Currency
        if (isset($data['currency']) && !empty($data['currency']))
            $model->currency_id = $this->getCurrencyIdByName($data['currency']);


        // Update product variables and eav attributes.
        $attributes = new CsvAttributesProcessor($model, $data);

        if ($model->validate()) {

            $categories = [$category_id];

            if (isset($data['additionalCategories']))
                $categories = array_merge($categories, $this->getAdditionalCategories($data['additionalCategories']));

            //if (!$newProduct) {
            foreach ($model->categorization as $c)
                $categories[] = $c->category;
            $categories = array_unique($categories);
            //}


            // Save product
            $model->save();


            // Set prices
            if (isset($data['wholesale_prices']) && !empty($data['wholesale_prices'])) {
                $pricesList = explode(';', $data['wholesale_prices']);
                $p = [];
                foreach ($pricesList as $n => $price) {
                    if (!empty($price)) {
                        list($priceValue, $num) = explode('=', $price);
                        $p[] = ['from' => $num, 'value' => $priceValue];
                    }
                }
                $model->processPrices($p);

            }
            // Update EAV data
            $attributes->save();

            // Update categories
            $model->setCategories($categories, $category_id);

            if (isset($data['image']) && !empty($data['image'])) {
                if ($this->validateImage($data['image'])) {
                    /** @var ImageBehavior $model */
                    $imagesArray = explode(';', $data['image']);
                    foreach ($imagesArray as $n => $im) {
                        $image = CsvImage::create($im);
                        if ($image) {
                            $model->attachImage($image);
                            if ($this->deleteDownloadedImages) {
                                $image->deleteTempFile();
                            }
                        }

                    }
                }else{
                        $this->errors[] = [
                            'line' => $this->line,
                            'error' => 'error image'
                        ];
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
            $result[] = $this->getCategoryByPath(trim($path), true);
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
     * Find or create manufacturer
     * @param $name
     * @return integer
     */
    public function getManufacturerIdByName($name)
    {
        if (isset($this->manufacturerCache[$name]))
            return $this->manufacturerCache[$name];

        $query = Manufacturer::find()
            ->joinWith(['translations translate'])
            ->where(['translate.name' => trim($name)]);

        // ->where(['name' => $name]);

        $model = $query->one();
        if (!$model) {
            $model = new Manufacturer();
            $model->name = trim($name);
            $model->slug = CMS::slug($model->name);
            $model->save();
        }

        $this->manufacturerCache[$name] = $model->id;
        return $model->id;
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

        $query = Currency::find()->where(['iso' => trim($name)]);
        /** @var Currency $model */
        $model = $query->one();
		
        if (!$model)
            throw new Exception(Yii::t('csv/default','NO_FIND_CURRENCY',$name));

        $this->currencyCache[$name] = $model->id;
        return $model->id;
    }

    /**
     * Find or create supplier
     * @param string $name
     * @return integer
     */
    public function getSupplierIdByName($name)
    {
        if (isset($this->supplierCache[$name]))
            return $this->supplierCache[$name];

        $query = Supplier::find()->where(['name' => trim($name)]);

        $model = $query->one();
        if (!$model) {
            $model = new Supplier();
            $model->name = $name;
            $model->save(false);
        }

        $this->supplierCache[$name] = $model->id;
        return $model->id;
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
     * @param $path string Main/Music/Rock
     * @return integer category id
     */
    protected function getCategoryByPath($path, $addition = false)
    {

        if (isset($this->categoriesPathCache[$path]))
            return $this->categoriesPathCache[$path];

        if ($this->rootCategory === null)
            $this->rootCategory = Category::findOne(1);


        $result = preg_split($this->subCategoryPattern, $path, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $result = array_map('stripcslashes', $result);


        $parent = $this->rootCategory;

        /** @var \panix\engine\behaviors\nestedsets\NestedSetsBehavior $model */
        $model = Category::find()
            ->joinWith(['translations translate'])
            ->where(['translate.name' => trim($result[0])])
            ->one();
        if (!$model) {
            $model = new Category;
            $model->name = trim($result[0]);
            $model->slug = CMS::slug($model->name);
            $model->appendTo($parent);
        }
        $first_model = $model;
        unset($result[0]);

        foreach ($result as $k => $name) {
            $model = $first_model->descendants()
                ->joinWith(['translations'])
                ->where([CategoryTranslate::tableName() . '.name' => trim($name)])
                //->where(['name'=>trim($name)]) //One language
                ->one();
            $parent = $first_model;
            if (!$model) {
                $model = new Category;
                $model->name = $name;
                $model->slug = CMS::slug($model->name);
                $model->appendTo($parent);
            }

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
     * @return array e.g array(key=>value)
     */
    protected function prepareRow($row)
    {
        $row = array_map('trim', $row);
        $row = array_combine($this->csv_columns, $row);
        $row['created_at'] = time();
        $row['updated_at'] = time();//date('Y-m-d H:i:s');

        return array_filter($row); // Remove empty keys and return result
    }

    /**
     * Read csv file.
     * Check encoding. If !utf8 - convert.
     * @return resource csv file
     */
    protected function getFileHandler()
    {
        $test_content = file_get_contents($this->file);
        $is_utf8 = mb_detect_encoding($test_content, 'UTF-8', true);

        if ($is_utf8 == false) {
            // Convert all file content to utf-8 encoding
            $content = iconv('cp1251', 'utf-8', $test_content);
            $this->fileHandler = tmpfile();
            fwrite($this->fileHandler, $content);
            fseek($this->fileHandler, 0);
        } else
            $this->fileHandler = fopen($this->file, 'r');
        return $this->fileHandler;
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
     * @param string $eav_prefix
     * @return array
     */
    public function getImportableAttributes($eav_prefix = '')
    {
        $attributes = [];
        $units = '';
        foreach ((new Product)->getUnits() as $id => $unit) {
            $units .= '<code style="font-size: inherit">' . $unit . '</code><br/>';
        }
        $shop_config = Yii::$app->settings->get('shop');
        $attributes['type'] = Yii::t('shop/Product', 'TYPE_ID');

        //if (!$shop_config['auto_gen_url']) {
        $attributes['name'] = Yii::t('shop/Product', 'NAME');
        // }
        $attributes['currency'] = Yii::t('shop/Product', 'CURRENCY_ID');
        $attributes['category'] = Yii::t('app/default', 'Категория. Если указанной категории не будет в базе она добавится автоматически.');
        $attributes['additionalCategories'] = Yii::t('app/default', 'Доп. Категории разделяются точкой с запятой <code>;</code>. На пример <code>MyCategory;MyCategory/MyCategorySub</code>.');
        $attributes['manufacturer'] = Yii::t('app/default', 'Производитель. Если указанного производителя не будет в базе он добавится автоматически.');
        $attributes['supplier'] = Yii::t('shop/Product', 'SUPPLIER_ID');
        $attributes['sku'] = Yii::t('shop/Product', 'SKU');
        $attributes['price'] = Yii::t('shop/Product', 'PRICE');
        $attributes['wholesale_prices'] = Yii::t('csv/default', 'WHOLESALE_PRICE');
        $attributes['unit'] = Yii::t('shop/Product', 'UNIT') . '<br/>' . $units;
        $attributes['switch'] = Yii::t('app/default', 'Скрыть или показать. Принимает значение <code>1</code> - показать <code>0</code> - скрыть.');
        $attributes['image'] = Yii::t('app/default', 'Изображение (можно указать несколько изображений). Пример: <code>pic1.jpg;pic2.jpg</code> разделяя название изображений символом "<code>;</code>" (точка с запятой). Первое изображение <b>pic1.jpg</b> будет являться главным. <div class="text-danger"><i class="flaticon-warning"></i> Также стоит помнить что не один из остальных товаров не должен использовать эти изображения.</div>');
        $attributes['full_description'] = Yii::t('app/default', 'Полное описание HTML');
        $attributes['quantity'] = Yii::t('app/default', 'Количество на складе.<br/>По умолчанию <code>1</code>, от 0 до 99999');
        $attributes['availability'] = Yii::t('app/default', 'Доступность. Принимает значение <code>1</code> - есть на складе, <code>2</code> - нет на складе, <code>3</code> - под заказ.<br/>По умолчанию<code>1</code> - есть на складе');
        //$attributes['created_at'] = Yii::t('app/default', 'Дата создания');
        // $attributes['updated_at'] = Yii::t('app/default', 'Дата обновления');
        foreach (Attribute::find()->joinWith(['translations'])->asArray()->all() as $attr) {
            $attributes[$eav_prefix . $attr['name']] = $attr['translations'][0]['title'];
        }
        return $attributes;
    }

    public function getExportAttributes($eav_prefix = '', $type_id)
    {

        $units = '';
        foreach ((new Product)->getUnits() as $id => $unit) {
            $units .= '<code style="font-size: inherit">' . $unit . '</code><br/>';
        }
        $attributes = [];
        $shop_config = Yii::$app->settings->get('shop');
        if (!Yii::$app->settings->get('csv', 'use_type')) {
            $attributes['type'] = Yii::t('shop/Product', 'TYPE_ID');
        }
        //if (!$shop_config['auto_gen_url']) {
        $attributes['name'] = Yii::t('shop/Product', 'NAME');
        // }
        $attributes['currency'] = Yii::t('shop/Product', 'CURRENCY_ID');
        $attributes['category'] = Yii::t('app/default', 'Категория. Если указанной категории не будет в базе она добавится автоматически.');
        $attributes['additionalCategories'] = Yii::t('app/default', 'Доп. Категории разделяются точкой с запятой <code style="font-size: inherit">;</code><br/>Например &mdash; <code style="font-size: inherit">MyCategory;MyCategory/MyCategorySub</code>.');
        $attributes['manufacturer'] = Yii::t('app/default', 'Производитель. Если указанного производителя не будет в базе он добавится автоматически.');
        $attributes['supplier'] = Yii::t('shop/Product', 'SUPPLIER_ID');
        $attributes['sku'] = Yii::t('shop/Product', 'SKU');
        $attributes['price'] = Yii::t('shop/Product', 'PRICE');
        $attributes['wholesale_prices'] = Yii::t('csv/default', 'WHOLESALE_PRICE');


        $attributes['unit'] = Yii::t('shop/Product', 'UNIT') . '<br/>' . $units;
        $attributes['switch'] = Yii::t('app/default', 'Скрыть или показать. Принимает значение<br/><code style="font-size: inherit">1</code> &mdash; показать<br/><code style="font-size: inherit">0</code> &mdash; скрыть');
        $attributes['image'] = Yii::t('app/default', 'Изображение (можно указать несколько изображений). Пример: <code style="font-size: inherit">pic1.jpg;pic2.jpg</code> разделяя название изображений символом "<code style="font-size: inherit">;</code>" (точка с запятой). Первое изображение <b>pic1.jpg</b> будет являться главным. <div class="text-danger"><i class="flaticon-warning"></i> Также стоит помнить что не один из остальных товаров не должен использовать эти изображения.</div>');
        $attributes['full_description'] = Yii::t('app/default', 'Полное описание HTML');
        $attributes['quantity'] = Yii::t('app/default', 'Количество на складе.<br/>По умолчанию &mdash; <code style="font-size: inherit">1</code>, от 0 до 99999');
        $attributes['availability'] = Yii::t('app/default', 'Наличие.<br/>Принимает значение<br/><code style="font-size: inherit">1</code> &mdash; есть на складе <strong>(default)</strong><br/><code style="font-size: inherit">2</code> &mdash; нет на складе<br/><code style="font-size: inherit">3</code> &mdash; под заказ.');
        //$attributes['created_at'] = Yii::t('app/default', 'Дата создания');
        //$attributes['updated_at'] = Yii::t('app/default', 'Дата обновления');
        if ($type_id) {
            $type = ProductType::findOne($type_id);
            foreach ($type->shopAttributes as $attr) {
                $attributes[$eav_prefix . $attr->name] = $attr->title;
            }
        } else {
            foreach (Attribute::find()->joinWith(['translations'])->asArray()->all() as $attr) {
                $attributes[$eav_prefix . $attr['name']] = $attr['translations'][0]['title'];
            }
        }

        return $attributes;
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
