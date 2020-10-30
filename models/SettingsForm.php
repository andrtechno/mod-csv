<?php

namespace panix\mod\csv\models;

use panix\engine\CMS;
use Yii;
use panix\engine\SettingsModel;

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
    //public $google_token;
    public $google_sheet_id;
    public $google_sheet_list;
    public $google_service;

    public function rules()
    {
        return [
            [['pagenum', 'indent_row', 'indent_column'], 'required'],
            [['indent_column', 'indent_row'], 'integer', 'min' => 1],
            [['ignore_columns', 'google_sheet_id', 'google_sheet_list','google_service'], 'string'],
            [['google_sheet_id', 'google_sheet_list'], 'trim'],

            [['google_sheet_id'], 'connectValidation'],
            //[['google_token'], 'file', 'skipOnEmpty' => true, 'extensions' => ['json']],
        ];
    }

    public static function defaultSettings()
    {
        return [
            'pagenum' => 300,
            'indent_row' => 1,
            'indent_column' => 1,
            'ignore_columns' => '',
            //'google_token' => '',
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

    /**
     * @return \Google_Client|mixed
     */
    public function getGoogleClient()
    {
        $config['credentials']=Yii::$app->runtimePath . '/shopiumbot-1595272867229-5e5d50e9a483.json';
       // if (file_exists($config['credentials'])) {
            try {

                $config['client_id']='113080523440486524239';
                $config['client_secret']='5e5d50e9a48361d54557f74a4949fd1b82d61d8e';
                //$config['client_email']='shopiumbot@shopiumbot-1595272867229.iam.gserviceaccount.com';
                $client = new \Google_Client($config);

                $client->useApplicationDefaultCredentials();
                $client->setApplicationName("Something to do with my representatives");
                $client->setScopes(['https://spreadsheets.google.com/feeds']); //'https://www.googleapis.com/auth/drive',



             //   if ($client->isAccessTokenExpired()) {
             //       $client->refreshTokenWithAssertion();
             //  }
               // CMS::dump($client);die;


                return $client;
            } catch (\Google_Service_Exception $e) {
                $error = json_decode($e->getMessage());
                // \panix\engine\CMS::dump($error->error->message);
                return $error;
            }
      //  }else{
      //      return false;
      //  }
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
