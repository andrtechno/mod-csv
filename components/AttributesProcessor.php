<?php

namespace panix\mod\csv\components;

use Yii;
use yii\base\Component;
use yii\base\Exception;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\AttributeOption;
use panix\mod\shop\models\Product;
use panix\mod\shop\models\TypeAttribute;
use panix\mod\shop\models\ProductType;
use panix\engine\CMS;

/**
 * Class AttributesProcessor handles Product class attributes and
 * EAV attributes.
 */
class AttributesProcessor extends Component
{

    /**
     * @var Product|\panix\mod\shop\components\EavBehavior
     */
    public $model;

    /**
     * @var array csv row.
     */
    public $data;

    /**
     * @var array
     */
    const skipNames = [
        'наименование',
        'артикул',
        'категория',
        // 'Тип',
        'цена',
        'лейблы',
        'цена закупки',
        'бренд',
        'валюта',
        'фото',
        'доп. категории',
        'wholesale_prices',
        'описание',
        'unit',
        'switch',
        'количество',
        'поставщик',
        'наличие',
        'скидка',
        'конфигурация',
        'связи',
        'delete',
        'ID',
        '__hash'
    ];

    /**
     * @var array Attribute models.
     */
    protected $attributesCache = [];

    /**
     * @var array AttributeOption models.
     */
    protected $optionsCache = [];

    /**
     * @var array for eav attributes to be saved.
     */
    protected $eav;

    /**
     * @param Product $product
     * @param array $data
     */
    public function __construct(Product $product, array $data)
    {
        $this->model = $product;
        $this->data = $data;
        $this->process();
        parent::__construct([]);
    }

    /**
     * Process each data row. First, try to assign value to products model,
     * if attributes does not exists - handle like eav attribute.
     * @return bool
     */
    public function process()
    {

        foreach ($this->data as $key => $val) {

            if (empty($val)) {
                $key = CMS::slug($key, '_');
                $this->model->deleteEavAttributes([$key], true);
                return true;
            }

            try {
                if (!in_array($key, self::skipNames) && !empty($val)) {
                    $this->model->$key = $val;
                }
            } catch (Exception $e) {
                // Process eav
                if (!in_array($key, self::skipNames)) {

                    //if (substr($key, 0, 4) === 'eav_')
                    //    $key = substr($key, 4);

                    $name = $key;
                    $key = CMS::slug($key, '_');

                    if (!empty($val)) {
                        $this->eav[$key] = $this->processEavData($name, $key, $val);
                    }
                }
            }
        }
    }


    /**
     * @param $attribute_name
     * @param $attribute_key
     * @param $attribute_value
     * @return array
     */
    public function processEavData($attribute_name, $attribute_key, $attribute_value)
    {
        $result = [];

        $attribute = $this->getAttributeByName($attribute_key, $attribute_name);

        $multipleTypes = [Attribute::TYPE_CHECKBOX_LIST, Attribute::TYPE_DROPDOWN, Attribute::TYPE_SELECT_MANY, Attribute::TYPE_COLOR];

        if (in_array($attribute->type, $multipleTypes)) {
            foreach (explode(';', $attribute_value) as $val) {
                $option = $this->getOption($attribute, $val);
                $result[] = $option->id;
            }
        } else {
            $option = $this->getOption($attribute, $attribute_value);
            $result[] = $option->value;
        }

        return $result;
    }

    /**
     * Find or create option by attribute and value.
     *
     * @param Attribute $attribute
     * @param $val
     * @return AttributeOption
     */
    public function getOption(Attribute $attribute, $val)
    {
        $val = trim($val);
        $cacheKey = sha1($attribute->id . $val);

        if (isset($this->optionsCache[$cacheKey]))
            return $this->optionsCache[$cacheKey];

        // Search for option
        $query = AttributeOption::find();


        $query->where(['attribute_id' => $attribute->id]);
        $query->andWhere(['value' => $val]);


        $option = $query->one();

        if (!$option) // Create new option
            $option = $this->addOptionToAttribute($attribute->id, $val);

        $this->optionsCache[$cacheKey] = $option;

        return $option;
    }

    /**
     * @param $attribute_id
     * @param $value
     * @return AttributeOption
     */
    public function addOptionToAttribute($attribute_id, $value)
    {
        $option = new AttributeOption;
        $option->attribute_id = $attribute_id;
        $option->value = $value;
        $option->save(false);

        return $option;
    }

    /**
     * @param $key
     * @param $name
     * @return Attribute
     */
    public function getAttributeByName($key, $name)
    {


        if (isset($this->attributesCache[$key]))
            return $this->attributesCache[$key];


        $attribute = Attribute::find()->where(['name' => $key])->one();

        if (!$attribute) {

            // Create new attribute
            $attribute = new Attribute;
            $attribute->title_ru = $name;
            $attribute->name = $key;
            $attribute->type = Attribute::TYPE_DROPDOWN;
            $attribute->save(false);

            // Add to type
            $typeAttribute = new TypeAttribute;
            $typeAttribute->type_id = $this->model->type_id;
            $typeAttribute->attribute_id = $attribute->id;
            $typeAttribute->save(false);
        }

        $this->attributesCache[$key] = $attribute;

        return $attribute;
    }

    /**
     * Append and save product attributes.
     */
    public function save()
    {
        if (!empty($this->eav))
            $this->model->setEavAttributes($this->eav, true);
    }


    public static function getImportExportData($eav_prefix = '', $type_id = null)
    {
        $attributes = [];
        $units = '';
        $product = new Product;
        foreach ($product->getUnits() as $id => $unit) {
            $units .= '<code>' . $unit . '</code><br/>';
        }

        $listLabel = '';
        foreach ($product::getLabelList() as $label_key => $label) {
            $listLabel .= "<code>{$label_key}</code> &mdash; {$label}<br/>";
        }

        $shop_config = Yii::$app->settings->get('shop');
        $attributes['ID'] = Yii::t('shop/Product', 'ID');
        $attributes['Наименование'] = Yii::t('shop/Product', 'NAME');
        $attributes['Связи'] = Yii::t('shop/Product', 'Связи') . ': Укажите список ID товаров для связи.<br/>Например: <code>12345;45678</code>';
        $attributes['Лейблы'] = Yii::t('shop/Product', 'LABEL') . '<br/>' . $listLabel . '<br/>Например: <code>top_sale;hit_sale</code>';
        $attributes['Категория'] = Yii::t('csv/default', 'Категория. Если указанной категории не будет в базе она добавится автоматически.');
        $attributes['Доп. Категории'] = Yii::t('csv/default', 'Доп. категории разделяются точкой с запятой <code>;</code>. Например <code>MyCategory;MyCategory/MyCategorySub</code>.');
        $attributes['Бренд'] = Yii::t('csv/default', 'Производитель. Если указанного производителя не будет в базе он добавится автоматически.');
        $attributes['Артикул'] = Yii::t('shop/Product', 'SKU');
        $attributes['Валюта'] = Yii::t('shop/Product', 'CURRENCY_ID') . '<br/>Например: <code>USD</code>';
        $attributes['Цена'] = Yii::t('shop/Product', 'PRICE');
        $attributes['Цена закупки'] = Yii::t('shop/Product', 'PRICE_PURCHASE');
        $attributes['Конфигурация'] = Yii::t('shop/Product', 'USE_CONFIGURATIONS') . ' (В ячейке необходимо указать название конфигурации, например <code>Вес</code>). Для удаления конфигурации из товара в ячейке необходимо указать - <code>no</code>.';
        $attributes['Скидка'] = Yii::t('shop/Product', 'DISCOUNT') . '<br/>Чтобы указать скидку в процентах необходимо указать: <code>\'10%</code>';
        $attributes['unit'] = Yii::t('shop/Product', 'UNIT') . '<br/>' . $units;
        $attributes['switch'] = Yii::t('csv/default', 'Скрыть или показать. Принимает значение <code>1</code> &mdash; показать <code>0</code> - скрыть.');
        $attributes['Фото'] = Yii::t('csv/default', 'Изображение (можно указать несколько изображений). Пример: <code>pic1.jpg;pic2.jpg</code> разделяя название изображений символом "<code>;</code>" (точка с запятой). Первое изображение <b>pic1.jpg</b> будет являться главным. <div class="alert alert-danger">Также стоит помнить что не один из остальных товаров не должен использовать эти изображения.</div>');
        $attributes['Описание'] = Yii::t('csv/default', 'Полное описание HTML');
        $attributes['Количество'] = Yii::t('csv/default', 'Количество на складе.<br/>По умолчанию <code>1</code>, от 0 до 99999');
        $attributes['Наличие'] = Yii::t('csv/default', 'Доступность.<br/><code>1</code> &mdash; есть в наличие <strong>(по умолчанию)</strong><br/><code>2</code> &mdash; под заказ<br/><code>3</code> &mdash; нет в наличие.');
        $attributes['delete'] = Yii::t('csv/default', 'Удаление товара.<br/><code>1</code> &mdash; удалить<br/><code>0</code> &mdash; не удалять');
        //$attributes['created_at'] = Yii::t('app/default', 'Дата создания');
        // $attributes['updated_at'] = Yii::t('app/default', 'Дата обновления');
        /*foreach (Attribute::find()->asArray()->all() as $attr) {
            $attributes[$eav_prefix . $attr['title']] = $attr['title'];
        }*/

        if ($type_id) {
            $type = ProductType::findOne($type_id);
            foreach ($type->shopAttributes as $attr) {
                $attributes[$eav_prefix . $attr->title_ru] = $attr->title_ru;
            }
        } else {
            foreach (Attribute::find()->asArray()->all() as $attr) {
                $attributes[$eav_prefix . $attr['title_ru']] = $attr['title_ru'];
            }
        }

        return $attributes;
    }

}

