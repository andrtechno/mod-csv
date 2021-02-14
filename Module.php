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
    public $import = ['class'=>'\panix\mod\csv\components\Importer'];
    public $export = ['class'=>'\panix\mod\csv\components\Exporter'];

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
        $app->setComponents([
            'queueSheets' => [
                'class' => 'yii\queue\db\Queue',
                //'tableName' => '{{%queue}}',
                'mutexTimeout' => 5,
                'ttr' => 5 * 60, // Максимальное время выполнения задания
                'attempts' => 3, // Максимальное кол-во попыток
                'deleteReleased' => false,
                'mutex' => \yii\mutex\MysqlMutex::class, // Мьютекс для синхронизации запросов
                'as log' => \yii\queue\LogBehavior::class,
                'commandClass' => \panix\mod\csv\components\queue\db\Command::class,
            ],
        ]);
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

    public function getImporter()
    {
        return Yii::createObject($this->import);
    }

    public function getExporter()
    {
        return Yii::createObject($this->export);
    }
}
