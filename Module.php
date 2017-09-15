<?php
namespace panix\mod\csv;

use Yii;
use panix\engine\WebModule;
class Module extends WebModule {

    public $icon = 'file-csv';

    public function getAdminMenu() {
        return [
            'shop' => [
                'items' => [
                    [
                        'label' => Yii::t('csv/default', 'MODULE_NAME'),
                        'url' => ['/admin/csv'],
                        'icon' => $this->icon,
                    // 'active' => $this->getIsActive('csv/default'),
                    ],
                ],
            ],
        ];
    }

    public function getAdminSidebar() {
        $mod = new \panix\engine\widgets\nav\Nav;
        $items = $mod->findMenu('shop');
        return $items['items'];
    }
    public function getInfo() {
        return [
            'label' => Yii::t('csv/default', 'MODULE_NAME'),
            'author' => 'andrew.panix@gmail.com',
            'version' => '1.0',
            'icon' => $this->icon,
            'description' => Yii::t('csv/default', 'MODULE_DESC'),
            'url' => ['/admin/csv'],
        ];
    }
}
