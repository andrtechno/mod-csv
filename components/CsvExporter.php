<?php

namespace panix\mod\csv\components;

use panix\mod\shop\models\ProductType;
use Yii;
use panix\mod\shop\models\Product;
use panix\mod\shop\models\Manufacturer;
use panix\engine\CMS;

use yii\helpers\Url;
use yii\web\Response;

class CsvExporter
{

    /**
     * @var array
     */
    public $rows = [];

    /**
     * @var string
     */
    public $delimiter = ";";

    /**
     * @var string
     */
    public $enclosure = '"';

    /**
     * Cache category path
     * @var array
     */
    public $categoryCache = [];

    /**
     * @var array
     */
    public $manufacturerCache = [];

    /**
     * @var array
     */
    public $supplierCache = [];

    /**
     * @var array
     */
    public $currencyCache = [];

    /**
     * @param array $attributes
     * @param $query \panix\mod\shop\models\query\ProductQuery
     */
    public function export(array $attributes, $query)
    {
        $this->rows[0] = $attributes;

        /*foreach ($this->rows[0] as &$v) {
            if (substr($v, 0, 4) === 'eav_')
                $v = substr($v, 4);
        }*/

        /** @var Product $p */
        foreach ($query->all() as $p) {
            $row = [];

            foreach ($attributes as $attr) {
                if ($attr === 'category') {
                    $value = $this->getCategory($p);
                } elseif ($attr === 'manufacturer') {
                    $value = $this->getManufacturer($p);
                } elseif ($attr === 'image') {
                    /** @var \panix\mod\images\behaviors\ImageBehavior|\panix\mod\images\models\Image $img */
                    $img = $p->getImage();
                    $value = ($img) ? $img->filePath : '';
                } elseif ($attr === 'additionalCategories') {
                    $value = $this->getAdditionalCategories($p);
                } elseif ($attr === 'type') {
                    $value = $p->type->name;
                } elseif ($attr === 'unit') {
                    $value = (isset($p->units[$p->$attr])) ? $p->units[$p->$attr] : NULL;
                } elseif ($attr === 'full_description') {
                    $value = <<<EOF
{$p->$attr}
EOF;
                } else {

                    $value = $p->$attr;

                }

                //  $row[$attr] = iconv('utf-8', 'cp1251', $value); //append iconv by panix
                $row[$attr] = $value; //append iconv by panix
            }

            array_push($this->rows, $row);
        }

        $this->proccessOutput();
    }

    /**
     * Get category path
     * @param Product $product
     * @return string
     */
    public function getCategory(Product $product)
    {

        $category = $product->mainCategory;
        if ($category) {
            if ($category && $category->id == 1)
                return '';

            if (isset($this->categoryCache[$category->id]))
                $this->categoryCache[$category->id];
            // foreach($category->excludeRoot()->ancestors()->findAll() as $test){
            //    VarDumper::dump($test->name);
            //}
            // die();
            $ancestors = $category->ancestors()->excludeRoot()->all();
            if (empty($ancestors))
                return $category->name;

            $result = array();
            foreach ($ancestors as $c)
                array_push($result, preg_replace('/\//', '\/', $c->name));
            array_push($result, preg_replace('/\//', '\/', $category->name));

            $this->categoryCache[$category->id] = implode('/', $result);

            return $this->categoryCache[$category->id];
        } else {
            return false;
        }
    }

    /**
     * @param Product $product
     * @return string
     */
    public function getAdditionalCategories(Product $product)
    {
        $mainCategory = $product->mainCategory;
        $categories = $product->categories;

        $result = array();
        foreach ($categories as $category) {
            if ($category->id !== $mainCategory->id) {
                $path = array();
                $ancestors = $category->ancestors()->excludeRoot()->all();
                foreach ($ancestors as $c)
                    $path[] = preg_replace('/\//', '\/', $c->name);
                $path[] = preg_replace('/\//', '\/', $category->name);
                $result[] = implode('/', $path);
            }
        }

        if (!empty($result))
            return implode(';', $result);
        return '';
    }

    /**
     * Get manufacturer
     *
     * @param Product $product
     * @return mixed|string
     */
    public function getManufacturer(Product $product)
    {
        if (isset($this->manufacturerCache[$product->manufacturer_id]))
            return $this->manufacturerCache[$product->manufacturer_id];

        $product->manufacturer ? $result = $product->manufacturer->name : $result = '';
        $this->manufacturerCache[$product->manufacturer_id] = $result;
        return $result;
    }

    /**
     * Get Currency
     *
     * @param Product $product
     * @return mixed|string
     */
    public function getCurrency(Product $product)
    {
        if (isset($this->currencyCache[$product->currency_id]))
            return $this->currencyCache[$product->currency_id];

        $product->currency ? $result = $product->currency->iso : $result = '';
        $this->currencyCache[$product->currency_id] = $result;
        return $result;
    }

    /**
     * Get supplier
     *
     * @param Product $product
     * @return mixed|string
     */
    public function getSupplier(Product $product)
    {
        if (isset($this->supplierCache[$product->supplier_id]))
            return $this->supplierCache[$product->supplier_id];

        $product->supplier ? $result = $product->supplier->name : $result = '';
        $this->supplierCache[$product->supplier_id] = $result;
        return $result;
    }

    /**
     * Create CSV file
     */
    public function proccessOutput()
    {

        $get = Yii::$app->request->get('FilterForm');
        $filename = '';
        if (isset($get['manufacturer_id'])) {
            if ($get['manufacturer_id'] == 'all') {
                $filename .= 'all_';
            } else {
                $manufacturer = Manufacturer::findOne($get['manufacturer_id']);
                if ($manufacturer) {
                    $filename .= $manufacturer->name . '_';
                }

            }
        }


        if ($get['type_id']) {
            $type = ProductType::findOne($get['type_id']);
            if ($type) {
                $filename .= $type->name . '_';
            }

        }


        $filename .= '(' . CMS::date() . ')';


        if (Yii::$app->request->get('page')) {
            $filename .= '_page-' . Yii::$app->request->get('page');
        }
        // $response = Yii::$app->response;
        // $response->format = Response::FORMAT_RAW;
        // $response->charset = 'utf-8';
        // $response->headers->set('Content-Type', 'application/octet-stream; charset=utf-8');
        header("Content-type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");


        // $headers = Yii::$app->response->headers;
        //  $headers->add('Pragma111', 'no-cache');


        $csvString = '';
        foreach ($this->rows as $row) {
            foreach ($row as $l) {
                // $csvString .= $this->enclosure . str_replace($this->enclosure, $this->enclosure . $this->enclosure, $l) . $this->enclosure . $this->delimiter;
                //$csvString .= $this->enclosure . str_replace($this->enclosure, $this->enclosure . $this->enclosure, mb_convert_encoding($l, 'UTF-8', 'Windows-1251')) . $this->enclosure . $this->delimiter;
                echo $this->enclosure . str_replace($this->enclosure, $this->enclosure . $this->enclosure, $l) . $this->enclosure . $this->delimiter;
            }
            // $csvString .= PHP_EOL;
            echo PHP_EOL;
        }

        // echo $csvString;
        die;

        /*return $response->sendContentAsFile($csvString, $filename . '.csv', [
            'mimeType' => 'application/octet-stream',
             'inline'   => false
        ]);*/

    }

}
