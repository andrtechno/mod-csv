<?php

namespace panix\mod\csv\components;


use panix\mod\shop\components\ExternalFinder;
use Yii;
use panix\engine\CMS;
use panix\mod\shop\models\Product;


/**
 * Import products from xls/xlsx format
 * Images must be located at ./uploads/importImages
 */
class Importer extends BaseImporter implements IImporter
{
    public $required = ['наименование', 'категория', 'цена'];
    public function execute($data, $type)
    {

        $category_id = 1;

     /*   $newProduct = false;


        $full_name = $data['бренд'] . $data['артикул'];

        //$model = $this->external->getObject(ExternalFinder::OBJECT_PRODUCT, $data['Наименование']);
        $this->model = $this->external->getObject(ExternalFinder::OBJECT_PRODUCT, $full_name);
        // $model = $query->one();
        $hasDeleted = false;

        if (!$this->model) {
            $newProduct = true;
            $this->model = new Product;
            $this->totalProductCount++;
            if (isset($data['delete']) && $data['delete']) {
                $hasDeleted = true;
            }
        } else {
            if (isset($data['delete']) && $data['delete']) {
                $this->stats['deleted']++;
                $hasDeleted = true;
                $this->model->delete();
            }
        }

        if (!$hasDeleted) {*/
            if (isset($data['категория']) || !empty($data['категория'])) {
                $category_id = $this->getCategoryByPath($data['категория']);
            }
            // Process product type
            $this->model->type_id = $this->getTypeIdByName($type);

            $this->model->main_category_id = $category_id;

            if (isset($data['switch']) && !empty($data['switch'])) {
                $this->model->switch = $data['switch'];
            } else {
                $this->model->switch = 1;
            }
            if (isset($data['цена']) && !empty($data['цена'])) {
                $this->model->price = $data['цена'];
            }

            if (isset($data['наименование']) && !empty($data['наименование'])) {
                $this->model->name = $data['наименование'];
            }


            if (isset($data['цена закупки']) && !empty($data['цена закупки']))
                $this->model->price_purchase = $data['цена закупки'];

            if (isset($data['unit']) && !empty($data['unit']) && array_search(trim($data['unit']), $this->model->getUnits())) {
                $this->model->unit = array_search(trim($data['unit']), $this->model->getUnits());
            } else {
                $this->model->unit = 1;
            }


            // Manufacturer
            if (isset($data['бренд']) && !empty($data['бренд']))
                $this->model->manufacturer_id = $this->getManufacturerIdByName($data['бренд']);

            // Supplier
            if (isset($data['поставщик']) && !empty($data['поставщик']))
                $this->model->supplier_id = $this->getSupplierIdByName($data['поставщик']);

            if (isset($data['артикул']) && !empty($data['артикул']))
                $this->model->sku = $data['артикул'];

            if (isset($data['описание']) && !empty($data['описание']))
                $this->model->full_description = $data['описание'];

            if (isset($data['наличие']) && !empty($data['наличие']))
                $this->model->availability = (is_numeric($data['наличие'])) ? $data['наличие'] : 1;


            if (isset($data['конфигурация']) && !empty($data['конфигурация'])) {
                $this->model->use_configurations = 1;
            }


            // Currency
            if (isset($data['валюта']) && !empty($data['валюта']))
                $this->model->currency_id = $this->getCurrencyIdByName($data['валюта']);

            if (isset($data['скидка'])) {
                $this->model->discount = (!empty($data['скидка'])) ? $data['скидка'] : NULL;
            } else {
                $this->model->discount = NULL;
            }

            if (isset($data['лейблы'])) {
                $this->model->label = (!empty($data['лейблы'])) ? str_replace(';', ',', $data['лейблы']) : NULL;
            } else {
                $this->model->label = NULL;
            }

            // Update product variables and eav attributes.

            if ($this->productValidate()) {
                $attributes = new AttributesProcessor($this->model, $data);

                $this->stats[(($this->model->isNewRecord) ? 'create' : 'update')]++;

                // Update Related
                $this->processRelation();

                // Save product
                $this->model->save();

                // Create product external id
                if ($this->isNew === true) {
                    $this->external->createExternalId(ExternalFinder::OBJECT_PRODUCT, $this->model->id, $this->uniqueName);
                }

                // Set configuration
                $this->processConfiguration($attributes);


                // Update EAV data
                $attributes->save();

                // Set Categories
                $this->processCategories($category_id);

                // Set Images
                $this->processImages();

           // }
        }
    }


}
