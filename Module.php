<?php

namespace panix\mod\csv;

use panix\mod\admin\widgets\sidebar\BackendNav;
use Yii;
use panix\engine\WebModule;
use yii\base\BootstrapInterface;
use yii\web\GroupUrlRule;

class Module extends WebModule implements BootstrapInterface
{

    public $icon = 'file-csv';
    public $mailPath = '@csv/mail';
    public $uploadPath = '@uploads/csv_import_image';

    /**
     * @inheritdoc
     */
    public function bootstrap($app)
    {

        $groupUrlRule = new GroupUrlRule([
            'prefix' => $this->id,
            'rules' => [
                '<controller:[0-9a-zA-Z_\-]+>' => '<controller>/index',
                '<controller:[0-9a-zA-Z_\-]+>/<action:[0-9a-zA-Z_\-]+>' => '<controller>/<action>',
                //'<action:\w+>' => 'default/<action>',

            ],
        ]);
        $app->getUrlManager()->addRules($groupUrlRule->rules, false);
        $this->uploadPath = '@uploads/csv_import_image';
    }


    public function getAdminMenu()
    {
        return [
            'shop' => [
                'items' => [
                    'integration' => [
                        'items' => [
                            [
                                'label' => Yii::t('csv/default', 'MODULE_NAME'),
                                'url' => ['/admin/csv/default/import'],
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
