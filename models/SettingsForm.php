<?php

namespace panix\mod\csv\models;

use panix\engine\CMS;
use Yii;
use panix\engine\SettingsModel;
use yii\base\Exception;

/**
 * Class SettingsForm
 * @package panix\mod\csv\models
 */
class SettingsForm extends SettingsModel
{

    protected $module = 'csv';
    public static $category = 'csv';

    public $pagenum;
    public $indent_row;
    public $indent_column;
    public $ignore_columns;
    public $google_sheet_id;
    public $google_sheet_list;
    public $google_credentials;
    public $send_email;
    public $send_email_warn;
    public $send_email_error;

    public function rules()
    {
        return [
            [['pagenum', 'indent_row', 'indent_column'], 'required'],
            [['indent_column', 'indent_row'], 'integer', 'min' => 1],
            [['ignore_columns', 'google_sheet_id', 'google_sheet_list'], 'string'],
            [['google_sheet_id', 'google_sheet_list'], 'trim'],
            [['google_credentials'], 'file', 'skipOnEmpty' => true, 'extensions' => ['json']],
            [['google_sheet_id'], 'connectValidation'],
            [['send_email'], '\panix\engine\validators\EmailListValidator'],
            [['send_email_warn', 'send_email_error'], 'boolean'],
        ];
    }

    public static function defaultSettings()
    {
        return [
            'pagenum' => 300,
            'indent_row' => 1,
            'indent_column' => 1,
            'ignore_columns' => '',
            //'google_credentials' => '',
            'google_sheet_id' => '',
            'google_sheet_list' => ''
        ];
    }

    public function connectValidation($attribute)
    {

        try {
            $service = new \Google_Service_Sheets($this->getGoogleClient());
            $get = $service->spreadsheets->get($this->google_sheet_id);

            return true;
        } catch (\Google_Service_Exception $e) {
            $error = json_decode($e->getMessage());

            if ($error) {
                $this->addError($attribute, $error->error->message);
            } else {
                $this->addError($attribute, 'unknown error');
            }
        }

    }

    public function getCredentialsPath()
    {
        return Yii::$app->runtimePath . DIRECTORY_SEPARATOR . Yii::$app->settings->get('csv', 'google_credentials');
    }

    /**
     * @return \Google_Client|mixed
     */
    public function getGoogleClient()
    {


        $config['credentials'] = $this->getCredentialsPath();

        if (file_exists($config['credentials'])) {
            try {

                // $config['client_id']='102003670383502615058';
                // $config['client_secret']='128189e5944ed0db0f32936bc4356e4d951a30a9';
                $client = new \Google\Client($config);

                $client->useApplicationDefaultCredentials();
                $client->setApplicationName("Something to do with my representatives");
                $client->setScopes(['https://spreadsheets.google.com/feeds']); //'https://www.googleapis.com/auth/drive',


                if ($client->isAccessTokenExpired()) {
                    $client->fetchAccessTokenWithAssertion();
                }

                return $client;
            } catch (Exception $e) {
                $error = json_decode($e->getMessage());
                // \panix\engine\CMS::dump($error->error->message);
                return $error;
            }
        } else {
            return false;
        }
    }

    public function getSheetsDropDownList()
    {


        try {
            $sheets = $this->getSheets();
            if ($sheets) {
                $sheet = $sheets->getSheets();
                $sheetListDropDown = [];
                foreach ($sheet as $sh) {
                    $sheetListDropDown[$sh->getProperties()->getTitle()] = $sh->getProperties()->getTitle();
                }
                return $sheetListDropDown;
            } else {
                return [];
            }
        } catch (\Google_Service_Exception $e) {
            $error = json_decode($e->getMessage());
            // \panix\engine\CMS::dump($error->error->message);
            return [];
        }
    }

    public function getSheets()
    {
        if ($this->google_sheet_id) {
            $service = new \Google_Service_Sheets($this->getGoogleClient());
            return $service->spreadsheets->get($this->google_sheet_id);
        } else {
            return false;
        }
    }
}
