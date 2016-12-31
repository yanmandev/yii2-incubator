<?php

use yii\db\Migration;

class m000000_000000_operation_create_table extends Migration
{
    public function up()
    {
        $this->createTable('{{%incubator_operation}}', [
            'id' => $this->primaryKey(),
            'type' => $this->smallInteger()->notNull(),
            'status' => $this->smallInteger()->defaultValue(0)->notNull(),
            'transactionId' => $this->integer()->notNull(),
            'code' => $this->string()->notNull(),
            'username' => $this->string()->notNull(),
            'sum' => $this->money()->notNull(),
            'sumWithCommission' => $this->money(),
            'referral' => $this->string(),
            'referralSum' => $this->money(),
            'percent' => $this->money(),
            'currency' => $this->string(),
            'paymentSystem' => $this->string(),
            'processedAt' => $this->dateTime(),
            'debugData' => $this->text(),
        ]);
    }

    public function down()
    {
        $this->dropTable('{{%incubator_operation}}');
    }
}
