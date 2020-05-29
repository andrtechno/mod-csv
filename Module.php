<?php

namespace panix\mod\csv;

use panix\mod\admin\widgets\sidebar\BackendNav;
use Yii;
use panix\engine\WebModule;

class Module extends WebModule
{

    public $icon = 'file-csv';

    public function getAdminMenu()
    {
        return [
            'shop' => [
                'items' => [
                    'integration' => [
                        'items' => [
                            [
                                'label' => Yii::t('csv/default', 'MODULE_NAME'),
                                'url' => ['/admin/csv'],
                                'icon' => $this->icon,
                                'visible' => Yii::$app->user->can('/csv/admin/default/index') || Yii::$app->user->can('/csv/admin/default/*')
                            ],
                        ]
                    ]
                ]
            ]
        ];
    }

    public function getAdminSidebar()
    {
        return (new BackendNav())->findMenu('shop')['items'];
    }

    public function getInfo()
    {
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
