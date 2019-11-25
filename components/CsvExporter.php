<?php

namespace panix\mod\csv\components;

use Yii;
use panix\mod\shop\models\Product;
use panix\mod\shop\models\Manufacturer;
use panix\engine\CMS;

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
     * @param array $attributes
     */
    public function exportold(array $attributes)
    {
        $this->rows[0] = $attributes;

        foreach ($this->rows[0] as &$v) {
            if (substr($v, 0, 4) === 'eav_')
                $v = substr($v, 4);
        }

        $limit = 10;
        $total = ceil(Product::find()->count() / $limit);
        $offset = 0;

        for ($i = 0; $i <= $total; ++$i) {
            $products = Product::find()->limit($limit)->offset($offset)->all();

            foreach ($products as $p) {
                $row = [];

                foreach ($attributes as $attr) {
                    if ($attr === 'category') {
                        $value = $this->getCategory($p);
                    } elseif ($attr === 'manufacturer') {
                        $value = $this->getManufacturer($p);
                        //} elseif ($attr === 'image') {
                        //    $value = $p->mainImage ? $p->mainImage->name : '';
                    } elseif ($attr === 'additionalCategories') {
                        $value = $this->getAdditionalCategories($p);
                    } else {
                        $value = $p->$attr;
                    }

                  //  $row[$attr] = iconv('utf-8', 'cp1251', $value); //append iconv by panix
                    $row[$attr] = $value; //append iconv by panix
                }

                array_push($this->rows, $row);
            }

            $offset += $limit;
        }

        $this->proccessOutput();
    }

    /**
     * @param $attributes
     * @param $query \panix\mod\shop\models\query\ProductQuery
     */
    public function export($attributes, $query)
    {
        if ($attributes) {
            $this->rows[0] = $attributes;

            //foreach ($this->rows[0] as &$v) {
            //    if (substr($v, 0, 4) === 'eav_')
            //        $v = substr($v, 4);
            //}
            /** @var $p Product */
            foreach ($query->all() as $p) {
                $row = [];
                foreach ($attributes as $attr) {
                    if ($attr === 'category') {
                        $value = $this->getCategory($p);
                    } elseif ($attr === 'manufacturer') {
                        $value = $this->getManufacturer($p);
                        // } elseif ($attr === 'image') {
                        //     $value = $p->mainImage ? $p->mainImage->name : '';
                    } elseif ($attr === 'additionalCategories') {
                        $value = $this->getAdditionalCategories($p);
                    } else {
                        $value = $p->$attr;
                    }

                    //$row[$attr] = iconv('utf-8', 'cp1251', $value); //append iconv by panix
                    $row[$attr] = $value; //append iconv by panix
                }

                array_push($this->rows, $row);
            }

            $this->proccessOutput();
        }
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
     * Create CSV file
     */
    public function proccessOutput()
    {
        $filename = '';
        if (Yii::$app->request->get('manufacturer_id')) {
            if (Yii::$app->request->get('manufacturer_id') == 'all') {
                $filename .= 'all_';
            } else {
                $manufacturer = Manufacturer::findOne(Yii::$app->request->get('manufacturer_id'));
                $filename .= $manufacturer->name . '_';
            }
        }
        $filename .= '(' . CMS::date() . ')';


        if (Yii::$app->request->getQueryParam('page')) {
            $filename .= '_page-' . Yii::$app->request->getQueryParam('page');
        }
       // Yii::$app->response->format = Response::FORMAT_RAW;
       // header("Content-type: application/octet-stream");
        //header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");


        // $headers = Yii::$app->response->headers;
        //  $headers->add('Pragma111', 'no-cache');

$csvString = '';
        foreach ($this->rows as $row) {
            foreach ($row as $l) {
                $csvString .= $this->enclosure . str_replace($this->enclosure, $this->enclosure . $this->enclosure, mb_convert_encoding($l, 'UTF-8', 'Windows-1251')) . $this->enclosure . $this->delimiter;
                //echo $this->enclosure . str_replace($this->enclosure, $this->enclosure . $this->enclosure, $l) . $this->enclosure . $this->delimiter;
            }
            $csvString .=PHP_EOL;
           // echo PHP_EOL;
        }


        return \Yii::$app->response->sendContentAsFile($csvString, $filename.'.csv', [
            'mimeType' => 'application/octet-stream',
            //  'inline'   => false
        ]);

        //die;
    }

}
