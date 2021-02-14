<?php

namespace panix\mod\csv\components;



use panix\mod\shop\components\ExternalFinder;

use Yii;
use yii\base\Component;
use panix\engine\CMS;

use panix\mod\shop\models\Category;
use panix\mod\shop\models\Product;
use panix\mod\images\behaviors\ImageBehavior;


/**
 * ForsageImporter products from csv format
 * Images must be located at ./uploads/importImages
 */
class ForsageImporter extends BaseImporter
{

    /**
     * Create/update product from key=>value array
     * @param $data array of product attributes
     */
    public function importRow($data, $type)
    {

        $category_id = 1;
        $this->currentRow=$data;

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
            if (isset($data['delete']) && $data['delete']) {
                $hasDeleted = true;
            }
        } else {
            if (isset($data['delete']) && $data['delete']) {
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

            if (isset($data['скидка'])) {
                $model->discount = (!empty($data['скидка'])) ? $data['скидка'] : NULL;
            } else {
                $model->discount = NULL;
            }

            if (isset($data['лейблы'])) {
                $model->label = (!empty($data['лейблы'])) ? str_replace(';', ',', $data['лейблы']) : NULL;
            } else {
                $model->label = NULL;
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

                // Update Related
                if (isset($data['связи'])) {
                    $relatedIds = explode(';', $data['связи']);
                    $model->setRelatedProducts($relatedIds);
                }


                // Save product
                $model->save();

                if ($model->use_configurations) {

                    if (isset($data['конфигурация'])) {
                        $db = $model::getDb()->createCommand();
                        if (!empty($data['конфигурация']) && $data['конфигурация'] != 'no') {
                            $configure_attribute_list = explode(';', $data['конфигурация']);
                            $configureIds = [];
                            $db->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $model->id])->execute();
                            foreach ($configure_attribute_list as $configure_attribute) {
                                // $configure = Attribute::findOne(['name' => CMS::slug($configure_attribute, '_')]);
                                $configure = $attributes->getAttributeByName(CMS::slug($configure_attribute, '_'), $configure_attribute);

                                $db->insert('{{%shop__product_configurable_attributes}}', [
                                    'product_id' => $model->id,
                                    'attribute_id' => $configure->id
                                ])->execute();
                            }
                        } else {
                            $db->update(Product::tableName(), [
                                'use_configurations' => 0,
                            ], ['id' => $model->id])->execute();
                            $db->delete('{{%shop__product_configurable_attributes}}', ['product_id' => $model->id])->execute();
                        }

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


                $this->processImages($type);

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


}
