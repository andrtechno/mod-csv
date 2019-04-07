<?php
namespace panix\mod\csv\components;
use panix\mod\shop\models\Attribute;
use panix\mod\shop\models\AttributeOption;
use panix\mod\shop\models\Product;
use panix\mod\shop\models\TypeAttribute;

/**
 * Class CsvAttributesProcessor handles Product class attributes and
 * EAV attributes.
 */
class CsvAttributesProcessor extends \yii\base\Component {

    /**
     * @var Product
     */
    public $model;

    /**
     * @var array csv row.
     */
    public $data;

    /**
     * @var array
     */
    public $skipNames = array('category', 'type', 'manufacturer', 'image', 'additionalCategories');

    /**
     * @var array of ShopAttribute models.
     */
    protected $attributesCache = array();

    /**
     * @var array of ShopAttributeOption models.
     */
    protected $optionsCache = array();

    /**
     * @var array for eav attributes to be saved.
     */
    protected $eav;

    /**
     * @param Product $product
     * @param array $data
     */
    public function __construct(Product $product, array $data) {
        $this->model = $product;
        $this->data = $data;
        $this->process();
        parent::__construct([]);
    }

    /**
     * Process each data row. First, try to assign value to products model,
     * if attributes does not exists - handle like eav attribute.
     */
    public function process() {
        foreach ($this->data as $key => $val) {
            try {
                $this->model->$key = $val;
            } catch (CException $e) {
                // Process eav
                if (!in_array($key, $this->skipNames) && !empty($val)) {
                    $this->eav[$key] = $this->processEavData($key, $val);
                }
            }
        }
    }

    /**
     * @param $attribute_name
     * @param $attribute_value
     * @return string ShopAttributeOption id
     */
    public function processEavData($attribute_name, $attribute_value) {
        $result = array();
        $attribute = $this->getAttributeByName($attribute_name);

        $multipleTypes = array(Attribute::TYPE_CHECKBOX_LIST, Attribute::TYPE_DROPDOWN, Attribute::TYPE_SELECT_MANY);

        if (in_array($attribute->type, $multipleTypes)) {
            foreach (explode(',', $attribute_value) as $val) {
                $option = $this->getOption($attribute, $val);
                $result[] = $option->id;
            }
        } else {
            $option = $this->getOption($attribute, $attribute_value);
            $result[] = $option->id;
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
    public function getOption(Attribute $attribute, $val) {
        $val = trim($val);
        $cacheKey = sha1($attribute->id . $val);

        if (isset($this->optionsCache[$cacheKey]))
            return $this->optionsCache[$cacheKey];

        // Search for option
        $cr = new CDbCriteria;
        //$cr->with = 'option_translate';
        //$cr->compare('option_translate.value', $val);
        $cr->compare('t.value', $val);
        $cr->compare('t.attribute_id', $attribute->id);
        $option = AttributeOption::find($cr);

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
    public function addOptionToAttribute($attribute_id, $value) {
        $option = new AttributeOption;
        $option->attribute_id = $attribute_id;
        $option->value = $value;
        $option->save(false);

        return $option;
    }

    /**
     * @param $name
     * @return ShopAttribute
     */
    public function getAttributeByName($name) {
        if (isset($this->attributesCache[$name]))
            return $this->attributesCache[$name];

        $attribute = Attribute::find()->where(['name' => $name])->one();

        if (!$attribute) {
            // Create new attribute
            $attribute = new Attribute;
            $attribute->name = $name;
            $attribute->title = ucfirst(str_replace('_', ' ', $name));
            $attribute->type = Attribute::TYPE_DROPDOWN;
            $attribute->display_on_front = true;
            $attribute->save(false);

            // Add to type
            $typeAttribute = new TypeAttribute;
            $typeAttribute->type_id = $this->model->type_id;
            $typeAttribute->attribute_id = $attribute->id;
            $typeAttribute->save(false);
        }

        $this->attributesCache[$name] = $attribute;

        return $attribute;
    }

    /**
     * Append and save product attributes.
     */
    public function save() {
        if (!empty($this->eav))
            $this->model->setEavAttributes($this->eav, true);
    }

}

