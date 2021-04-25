<?php

/**
 * Generation migrate by PIXELION CMS
 *
 * @author PIXELION CMS development team <dev@pixelion.com.ua>
 * @link http://pixelion.com.ua PIXELION CMS
 *
 * Class m170908_104527_csv
 */

use panix\engine\db\Migration;

class m170908_104527_csv extends Migration
{


    public function up()
    {
        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci ENGINE=InnoDB';
        } else if ($this->db->driverName === 'pgsql') {
            $this->execute('CREATE EXTENSION IF NOT EXISTS "uuid-ossp"');
        }

        $this->createTable('{{%csv}}', [
            'id' => ($this->db->driverName === 'pgsql') ? "uuid DEFAULT uuid_generate_v4()" : $this->primaryKey()->unsigned(),
            'object_id' => $this->integer()->unsigned()->null(),
            'object_type' => $this->tinyInteger()->null(),
            'external_id' => ($this->db->driverName === 'mysql') ? $this->integer()->unsigned()->null() : $this->bigInteger()->unsigned()->null(),
            'external_data' => $this->string(255)->null(),
        ], $tableOptions);

        $this->createIndex('object_id', '{{%csv}}', 'object_id');
        $this->createIndex('object_type', '{{%csv}}', 'object_type');
        $this->createIndex('external_id', '{{%csv}}', 'external_id');
    }

    public function down()
    {
        $this->dropTable('{{%csv}}');
    }

}
