<?php

namespace panix\mod\csv\components;


interface IImporter
{

    public function execute($data,$type);

    public function processCategories(int $main_category_id);

    public function processImages();

    public function getManufacturerIdByName(string $name);

    public function getCurrencyIdByName(string $name);

    public function getCategoryByPath(string $path);

    public function getTypeIdByName(string $name);

    public function getSupplierIdByName(string $name);
}