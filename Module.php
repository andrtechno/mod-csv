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
                        'label' => 'csv',
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

}
