<?php

namespace panix\mod\csv\components;

use panix\engine\console\controllers\ConsoleController;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\query\ProductQuery;
use panix\mod\shop\models\RelatedProduct;
use PhpOffice\PhpSpreadsheet\Document\Properties;
use Yii;
use panix\mod\shop\models\Product;
use panix\mod\shop\models\Manufacturer;
use panix\mod\shop\models\ProductType;
use panix\engine\CMS;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;

class Exporter
{

    /**
     * @var array
     */
    public $rows = [];

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
    public $currencyCache = [];


    /**
     * @var array
     */
    public $typeCache = [];

    /**
     * @var \panix\mod\shop\models\query\ProductQuery
     */
    public $query;


    /**
     * @param bool $attributes
     */
    public function exportQueue(bool $attributes = true)
    {
        // $this->rows[0] = $attributes;
        $row = [];
        /** @var Product $p */
        foreach ($this->query->all() as $p) {


            //foreach ($attributes as $attr) {
            $row['ID'] = $p->id;
            $row['Наименование'] = $p->name;
            $row['Категория'] = $this->getCategory($p);
            $row['Цена'] = $p->price;
            $row['Цена закупки'] = $p->price_purchase;
            $row['Валюта'] = $this->getCurrency($p);

            $row['Бренд'] = $this->getManufacturer($p);

            /** @var \panix\mod\images\behaviors\ImageBehavior|\panix\mod\images\models\Image $img */
            $img = $p->getImage();
            $row['Фото'] = ($img) ? $img->filePath : NULL;
            $row['Категория'] = $this->getCategory($p);
            $row['Доп. Категории'] = $this->getAdditionalCategories($p);
            $row['Связи'] = $this->getRelatedProducts($p);


            $row['Артикул'] = $p->sku;

            $listLabels = explode(',', $p->label);
            $row['Лейблы'] = implode(';', $listLabels);
            $row['Наличие'] = $p->availability;
            $row['Количество'] = $p->quantity;
            $row['Описание'] = $p->full_description;

            $use_configurations = '';
            if ($p->use_configurations) {
                $attribute_id = $p->configurable_attributes;
                $attributeModels = Attribute::find()->where(['id' => $attribute_id])->all();
                if ($attributeModels) {
                    $list = [];
                    foreach ($attributeModels as $configure) {
                        $list[] = $configure->title_ru;
                    }
                    $use_configurations = implode(';', $list);
                }
            }
            $row['Конфигурация'] = $use_configurations;

            $wholesale_prices = [];
            $result = NULL;
            if (isset($p->prices)) {
                foreach ($p->prices as $wp) {
                    $price[] = $wp->value . '=' . $wp->from;
                }
                $result = implode(';', $wholesale_prices);
            }
            $row['wholesale_prices'] = $result;

            /*if (isset($p->units)) {
                $unit = (isset($p->units[$p->$attr])) ? $p->units[$p->$attr] : NULL;
            } else {
                $unit = NULL;
            }*/
            $row['unit'] = $p->units[$p->unit];
            $row['switch'] = $p->switch;

            if ($attributes) {
                foreach ($p->getEavAttributes() as $k => $attribute) {
                    $attr_name = 'eav_' . $k;
                    if ($p->{$attr_name}) {
                        $row[$k] = $p->{$attr_name}->value;
                    } else {
                        $row[$k] = '';
                    }
                }
            }

            // $row[$attr] = $value;
            // }

            array_push($this->rows, $row);
        }


    }


    /**
     * @param array $attributes
     * @param ProductType|null $type
     */
    public function export(array $attributes, $type = null)
    {
        $this->rows[0] = $attributes;

        /** @var Product $p */
        foreach ($this->query->all() as $p) {
            $row = [];

            foreach ($attributes as $attr) {
                if ($attr === 'ID') {
                    $value = $p->id;
                } elseif ($attr === 'Категория') {
                    $value = $this->getCategory($p);
                } elseif ($attr === 'Бренд') {
                    $value = $this->getManufacturer($p);
                } elseif ($attr === 'Фото') {
                    /** @var \panix\mod\images\behaviors\ImageBehavior|\panix\mod\images\models\Image $img */
                    $img = $p->getImage();
                    $value = ($img) ? $img->filePath : NULL;
                } elseif ($attr === 'Доп. Категории') {
                    $value = $this->getAdditionalCategories($p);
                } elseif ($attr === 'Связи') {
                    $value = $this->getRelatedProducts($p);
                } elseif ($attr === 'Наименование') {
                    $value = $p->name;
                } elseif ($attr === 'Цена') {
                    $value = $p->price;
                } elseif ($attr === 'Цена закупки') {
                    $value = $p->price_purchase;
                } elseif ($attr === 'Валюта') {
                    $value = $this->getCurrency($p);
                } elseif ($attr === 'Артикул') {
                    $value = $p->sku;
                } elseif ($attr === 'Лейблы') {
                    $listLabels = explode(',', $p->label);
                    $value = implode(';', $listLabels);
                } elseif ($attr === 'Наличие') {
                    $value = $p->availability;
                } elseif ($attr === 'Количество') {
                    $value = $p->quantity;
                } elseif ($attr === 'Описание') {
                    $value = $p->full_description;
                } elseif ($attr === 'Конфигурация') {
                    $value = '';
                    if ($p->use_configurations) {
                        $attribute_id = $p->configurable_attributes;
                        $attributeModels = Attribute::find()->where(['id' => $attribute_id])->all();
                        if ($attributeModels) {
                            $list = [];
                            foreach ($attributeModels as $configure) {
                                $list[] = $configure->title_ru;
                            }
                            $value = implode(';', $list);
                        }
                    }

                } elseif ($attr === 'wholesale_prices') {
                    $price = [];
                    $result = NULL;
                    if (isset($p->prices)) {
                        foreach ($p->prices as $wp) {
                            $price[] = $wp->value . '=' . $wp->from;
                        }
                        $result = implode(';', $price);
                    }
                    $value = $result;

                } elseif ($attr === 'unit') {
                    if (isset($p->units)) {
                        $value = (isset($p->units[$p->$attr])) ? $p->units[$p->$attr] : NULL;
                    } else {
                        $value = NULL;
                    }
                } elseif (in_array($attr, ['switch', 'custom_id'])) {
                    $value = $p->$attr;
                } else {
                    $name = CMS::slug($attr);


                    if ($p->{'eav_' . $name}) {
                        //CMS::dump($p->{'eav_' . $name});die;

                        $value = $p->{'eav_' . $name}->value;
                    } else {
                        //CMS::dump($name);die;
                        // CMS::dump($p->{'eav_' . $name});die;
                        $value = '';
                    }
                }

                //  $row[$attr] = iconv('utf-8', 'cp1251', $value); //append iconv by panix

                $row[$attr] = $value;
            }

            array_push($this->rows, $row);
        }


        $this->processOutput($type);


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

            $ancestors = $category->ancestors()->excludeRoot()->all();
            if (empty($ancestors))
                return $category->name;

            $result = [];
            foreach ($ancestors as $c)
                array_push($result, preg_replace('/\//', '\/', $c->name));
            array_push($result, preg_replace('/\//', '\/', $category->name));

            $this->categoryCache[$category->id] = implode('/', $result);

            return $this->categoryCache[$category->id];
        } else {
            return false;
        }
    }

    public function getRelatedProducts(Product $product)
    {
        $relateds = RelatedProduct::find()->where(['product_id' => $product->id])->all();
        $list = [];
        foreach ($relateds as $related) {
            $list[] = $related->related_id;
        }
        if ($list) {
            return implode(';', $list);
        }

        return '';
    }

    /**
     * @param Product $product
     * @return string
     */
    public function getAdditionalCategories(Product $product)
    {
        $mainCategory = $product->mainCategory;
        $categories = $product->categories;

        $result = [];
        if ($mainCategory) {
            foreach ($categories as $category) {
                if ($category->id !== $mainCategory->id) {
                    $path = [];
                    $ancestors = $category->ancestors()->excludeRoot()->all();
                    foreach ($ancestors as $c)
                        $path[] = preg_replace('/\//', '\/', $c->name);
                    $path[] = preg_replace('/\//', '\/', $category->name);
                    $result[] = implode('/', $path);
                }
            }
        }
        if (!empty($result)) {
            return implode(';', $result);
            //return $result[array_key_last($result)];
        }

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
     * Create CSV file
     * @param $type
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     */
    public function processOutput($type)
    {

        $get = Yii::$app->request->get('FilterForm');

        $format = $get['format'] ? $get['format'] : 'csv';
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

        if (isset($get['type_id'])) {
            $type = ProductType::findOne($get['type_id']);
            if ($type) {
                $filename .= $type->name . '_';
            }
        }

        $filename .= '(' . CMS::date() . ')';

        if (Yii::$app->request->get('page')) {
            $filename .= '_page-' . Yii::$app->request->get('page');
        }

        /*$ex = Helper::newSpreadsheet();
        $ex->setSheet(0,'List');
        $ex->addRow($this->rows[0]);
        unset($this->rows[0]);
        $ex->addRows($this->rows);

       $ex->output('My Excel');*/


        $spreadsheet = new Spreadsheet();

        $props = new Properties();
        // $props->setTitle($filename);
        $props->setCreator(Yii::$app->name);
        $props->setLastModifiedBy(Yii::$app->name);
        $props->setCompany(Yii::$app->name);
        //$props->setDescription(iconv('CP1251','utf-8',$filename));
        // $props->setDescription(mb_convert_encoding($filename, 'UTF-8', 'UTF-8'));


        $props->setCategory('ExportProducts');
        $spreadsheet->setProperties($props);

        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle($type->name);


        $index = 1;
        $alpha = 1;
        foreach ($this->rows as $key => $row) {
            $alpha = 1;
            foreach ($row as $l) {
                $sheet->setCellValue(Helper::num2alpha($alpha) . $index, $l);
                $alpha++;
            }
            $index++;
        }

        foreach (range(1, $alpha) as $columnID) {
            $sheet->getColumnDimension(Helper::num2alpha($columnID))->setAutoSize(true);
        }
        if ($format == 'xls') {
            $writer = new Xls($spreadsheet);
        } elseif ($format == 'xlsx') {
            $writer = new Xlsx($spreadsheet);
        } else {
            $writer = new Csv($spreadsheet);
        }

        if (!(Yii::$app instanceof ConsoleController)) {
            //header('Content-Type: application/vnd.ms-excel');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment;filename="' . $filename . '.' . $format . '"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
            /*return $response->sendContentAsFile($csvString, $filename . '.csv', [
                'mimeType' => 'application/octet-stream',
                 'inline'   => false
            ]);*/
        } else {

        }


        // die;


    }

}
