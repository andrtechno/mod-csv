<?php

namespace panix\mod\csv\components;

use panix\mod\images\behaviors\ImageBehavior;
use panix\mod\images\models\Image;
use Yii;
use panix\engine\CMS;
use panix\engine\Html;
use panix\mod\shop\models\Manufacturer;
use panix\mod\shop\models\ProductType;
use panix\mod\shop\models\translate\CategoryTranslate;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\Category;
use panix\mod\shop\models\Product;
use yii\helpers\VarDumper;

/**
 * Import products from csv format
 * Images must be located at ./uploads/importImages
 */
class CsvImporter extends \yii\base\Component
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
     * Columns from first line. e.g array(category,price,name,etc...)
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
    ];

    public $required = ['category', 'price', 'manufacturer', 'sku', 'type'];

    /*public function __construct($config = [])
    {
        $configure = Yii::$app->settings->get('csv');
        if (!$configure->use_type) {
            array_push($this->required, 'type');
        }
        parent::__construct($config);
    }*/

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
            $this->importRow($row);
            $this->line++;
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


        if (!$model) {
            $newProduct = true;
            $model = new Product;
            $this->stats['create']++;
        } else {
            $this->stats['update']++;

            if (isset($data['deleted']) && $data['deleted']) {
                $this->stats['deleted']++;
                $model->delete();
                //Yii::log('application','info',$model->id.'|'.$model->name);
            }

        }
        // print_r($data);die;


        //$model->name = $data['name'];
        //$model->slug = CMS::translit($data['name']);
        // Process product type
        $config = Yii::$app->settings->get('csv');
        $model->type_id = $this->getTypeIdByName($data['type']);
        $model->main_category_id = $category_id;
        $model->switch = isset($data['switch']) ? $data['switch'] : 1;


        if (isset($data['unit'])) {
            $model->unit = array_search($data['unit'], $model->getUnits());
        }


        // Manufacturer
        if (isset($data['manufacturer']) && !empty($data['manufacturer']))
            $model->manufacturer_id = $this->getManufacturerIdByName($data['manufacturer']);

        // Update product variables and eav attributes.
        $attributes = new CsvAttributesProcessor($model, $data);

        if ($model->validate()) {
            $categories = [$category_id];

            if (isset($data['additionalCategories']))
                $categories = array_merge($categories, $this->getAdditionalCategories($data['additionalCategories']));

            /*if (!$newProduct) {
                foreach ($model->categorization as $c)
                    $categories[] = $c->category;
                $categories = array_unique($categories);
            }*/


            // die;

            // Save product
            $model->save();

            // Update EAV data
            $attributes->save();

            // Update categories
            $model->setCategories($categories, $category_id);

            /** @var ImageBehavior $model */
            // Process product main image if product doesn't have one
            if (isset($data['image']) && !empty($data['image'])) {
                if (strpos($data['image'], ';')) {
                    $imagesArray = explode(';', $data['image']);
                    rsort($imagesArray);
                    foreach ($imagesArray as $n => $im) {
                        $image = CsvImage::create($im);
                        if ($image && $model->mainImage === null) {
                            $model->addImage($image);
                        }
                        if ($image && $this->deleteDownloadedImages)
                            $image->deleteTempFile();
                    }
                } else {
/*
                    $image = CsvImage::create($data['image']);
                    $isImage = $model->getImage();

                    if ($image && $isImage === null) {
                        $model->attachImage($image);
                    }
                    if ($image && $this->deleteDownloadedImages)
                        $image->deleteTempFile();*/
                }
            }
            // die;
        } else {
            $errors = $model->getErrors();

            $error = array_shift($errors);
            $this->errors[] = [
                'line' => $this->line,
                'error' => $error[0]
            ];
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

    /**
     * Find or create manufacturer
     * @param $name
     * @return integer
     */
    public function getManufacturerIdByName($name)
    {
        if (isset($this->manufacturerCache[$name]))
            return $this->manufacturerCache[$name];


        //$cr->with = array('man_translate');
        //$cr->compare('man_translate.name', $name);

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

        //$cr = new CDbCriteria;
        // $cr->compare('name', trim($result[0]));
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
        $row['created_at'] = time();//date('Y-m-d H:i:s');
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
        $shop_config = Yii::$app->settings->get('shop');

        $attributes['type'] = Yii::t('app', 'Тип см. {setting}', [
            'setting' => Html::a(Yii::t('app', 'SETTINGS'), ['/admin/csv/settings'])
        ]);

        //if (!$shop_config['auto_gen_url']) {
        $attributes['name'] = Yii::t('shop/Product', 'NAME');
        // }
        $attributes['category'] = Yii::t('app', 'Категория. Если указанной категории не будет в базе она добавится автоматически.');
        $attributes['additionalCategories'] = Yii::t('app', 'Доп. Категории разделяются точкой с запятой <code>;</code>. На пример <code>MyCategory;MyCategory/MyCategorySub</code>.');
        $attributes['manufacturer'] = Yii::t('app', 'Производитель. Если указанного производителя не будет в базе он добавится автоматически.');
        $attributes['sku'] = Yii::t('shop/Product', 'SKU');
        $attributes['price'] = Yii::t('shop/Product', 'PRICE');
        $attributes['switch'] = Yii::t('app', 'Скрыть или показать. Принимает значение <code>1</code> - показать <code>0</code> - скрыть.');
        $attributes['image'] = Yii::t('app', 'Изображение (можно указать несколько изображений). Пример: <code>pic1.jpg;pic2.jpg</code> разделя название изображений символом "<code>;</code>" (точка с запятой). Первое изображение <b>pic1.jpg</b> будет являться главным. <div class="text-danger"><i class="flaticon-warning"></i> Также стоит помнить что не один из остальных товаров не должен использовать эти изображения.</div>');
        $attributes['full_description'] = Yii::t('app', 'Полное описание HTML');
        $attributes['quantity'] = Yii::t('app', 'Количество на складе.<br/>По умолча́нию<code>1</code>');
        $attributes['availability'] = Yii::t('app', 'Доступность. Принимает значение <code>1</code> - есть на складе, <code>2</code> - нет на складе, <code>3</code> - под заказ.<br/>По умолча́нию<code>1</code> - есть на складе');
        //$attributes['created_at'] = Yii::t('app', 'Дата создания');
        // $attributes['updated_at'] = Yii::t('app', 'Дата обновления');
        foreach (Attribute::find()->joinWith(['translations'])->asArray()->all() as $attr) {
            $attributes[$eav_prefix . $attr['name']] = $attr['translations'][0]['title'];
        }
        return $attributes;
    }

    public function getExportAttributes($eav_prefix = '', $type_id)
    {

        $units = '';
        foreach ((new Product)->getUnits() as $id => $unit) {
            $units .= '<code style="font-size: inherit">' . $id . '</code> &mdash; ' . $unit . '<br/>';
        }
        $attributes = [];
        $shop_config = Yii::$app->settings->get('shop');
        if (!Yii::$app->settings->get('csv', 'use_type')) {
            $attributes['type'] = Yii::t('app', 'Тип см. {setting}', [
                'setting' => Html::a(Yii::t('app', 'SETTINGS'), ['/admin/csv/settings'])
            ]);
        }
        //if (!$shop_config['auto_gen_url']) {
        $attributes['name'] = Yii::t('shop/Product', 'NAME');
        // }
        $attributes['category'] = Yii::t('app', 'Категория. Если указанной категории не будет в базе она добавится автоматически.');
        $attributes['additionalCategories'] = Yii::t('app', 'Доп. Категории разделяются точкой с запятой <code style="font-size: inherit">;</code><br/>Например &mdash; <code style="font-size: inherit">MyCategory;MyCategory/MyCategorySub</code>.');
        $attributes['manufacturer'] = Yii::t('app', 'Производитель. Если указанного производителя не будет в базе он добавится автоматически.');
        $attributes['sku'] = Yii::t('shop/Product', 'SKU');
        $attributes['price'] = Yii::t('shop/Product', 'PRICE');
        $attributes['unit'] = Yii::t('shop/Product', 'UNIT') . '<br/>' . $units;
        $attributes['switch'] = Yii::t('app', 'Скрыть или показать. Принимает значение<br/><code style="font-size: inherit">1</code> &mdash; показать<br/><code style="font-size: inherit">0</code> &mdash; скрыть.');
        $attributes['image'] = Yii::t('app', 'Изображение (можно указать несколько изображений). Пример: <code style="font-size: inherit">pic1.jpg;pic2.jpg</code> разделя название изображений символом "<code style="font-size: inherit">;</code>" (точка с запятой). Первое изображение <b>pic1.jpg</b> будет являться главным. <div class="text-danger"><i class="flaticon-warning"></i> Также стоит помнить что не один из остальных товаров не должен использовать эти изображения.</div>');
        $attributes['full_description'] = Yii::t('app', 'Полное описание HTML');
        $attributes['quantity'] = Yii::t('app', 'Количество на складе.<br/>По умолчанию &mdash; <code style="font-size: inherit">1</code>');
        $attributes['availability'] = Yii::t('app', 'Наличие.<br/>Принимает значение<br/><code style="font-size: inherit">1</code> &mdash; есть на складе <strong>(default)</strong><br/><code style="font-size: inherit">2</code> &mdash; нет на складе<br/><code style="font-size: inherit">3</code> &mdash; под заказ.');
        //$attributes['created_at'] = Yii::t('app', 'Дата создания');
        //$attributes['updated_at'] = Yii::t('app', 'Дата обновления');
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
