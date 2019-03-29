<?php

namespace panix\mod\csv;

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
                                'url' => ['/csv'],
                                'icon' => $this->icon,
                                // 'active' => $this->getIsActive('csv/default'),
                            ],
                        ]
                    ]
                ]
            ]
        ];
    }

    public function getAdminSidebar()
    {
        return (new \panix\engine\bootstrap\BackendNav)->findMenu('shop')['items'];
    }

    public function getInfo()
    {
        return [
            'label' => Yii::t('csv/default', 'MODULE_NAME'),
            'author' => 'andrew.panix@gmail.com',
            'version' => '1.0',
            'icon' => $this->icon,
            'description' => Yii::t('csv/default', 'MODULE_DESC'),
            'url' => ['/csv'],
        ];
    }
}
