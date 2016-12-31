<?php

namespace yanpapayan\incubator\models;

use yanpapayan\incubator\Api;
use yanpapayan\incubator\events\DepositEvent;
use Yii;
use yii\db\Exception;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;

/**
 * This is the model class for table "{{%incubator_operation}}".
 *
 * @property integer $id
 * @property integer $type
 * @property integer $status
 * @property integer $transactionId
 * @property string $code
 * @property string $username
 * @property string $sum
 * @property string $sumWithCommission
 * @property string $referral
 * @property string $referralSum
 * @property string $percent
 * @property string $currency
 * @property string $paymentSystem
 * @property string $processedAt
 * @property string $debugData
 */
class Operation extends \yii\db\ActiveRecord
{
    const STATUS_NEW = 0;
    const STATUS_CANCELLED = 1;
    const STATUS_PENDING = 3;
    const STATUS_PROCESSING = 5;
    const STATUS_ERROR = 7;
    const STATUS_PROCESSED = 9;

    const TYPE_DEPOSIT = 1;
    const TYPE_OUTPUT = 2;
    const TYPE_REFERRALS = 3;

    /** @var Api */
    public $api;

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->api = Yii::$app->get('incubator');
        $this->api->off(DepositEvent::EVENT_INVOICE_PAYMENT);
        $this->api->on(DepositEvent::EVENT_INVOICE_PAYMENT, [$this, 'handleInvoicePayment']);
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%incubator_operation}}';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'transactionId', 'username', 'sum'], 'required'],
            [['type', 'status', 'transactionId'], 'integer'],
            [['sum', 'sumWithCommission', 'referralSum', 'percent'], 'number'],
            [['processedAt'], 'safe'],
            [['debugData'], 'string'],
            [['code', 'username', 'referral', 'currency', 'paymentSystem'], 'string', 'max' => 255],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('app', 'ID'),
            'type' => Yii::t('app', 'Type'),
            'status' => Yii::t('app', 'Status'),
            'transactionId' => Yii::t('app', 'Transaction ID'),
            'code' => Yii::t('app', 'Code'),
            'username' => Yii::t('app', 'Username'),
            'sum' => Yii::t('app', 'Sum'),
            'sumWithCommission' => Yii::t('app', 'Sum With Commission'),
            'referral' => Yii::t('app', 'Referral'),
            'referralSum' => Yii::t('app', 'Referral Sum'),
            'percent' => Yii::t('app', 'Percent'),
            'currency' => Yii::t('app', 'Currency'),
            'paymentSystem' => Yii::t('app', 'Payment System'),
            'processedAt' => Yii::t('app', 'Processed At'),
            'debugData' => Yii::t('app', 'Debug Data'),
        ];
    }

    /**
     * @inheritdoc
     */
    public function beforeSave($insert)
    {
        if ($insert) {
            $this->code = $this->api->encodeOperationId($this->transactionId, $this->username);
        }
        return parent::beforeSave($insert);
    }

    /**
     * @param $operationId
     * @param $username
     * @param $sum
     * @param $currency
     * @param string $paymentSystem
     * @return Operation|null
     * @throws \Exception
     */
    public static function deposit($operationId, $username, $sum, $currency, $paymentSystem = 'PMEUR')
    {
        $model = new self;
        $model->type = self::TYPE_DEPOSIT;
        $model->username = $username;
        $model->sum = $sum;
        $model->currency = $currency;
        $model->paymentSystem = $paymentSystem;
        $model->transactionId = $operationId;
        return $model->addQueue();
    }

    /**
     * @param $operationId
     * @param $username
     * @param $sum
     * @param $sumWithCommission
     * @param $currency
     * @param string $paymentSystem
     * @return Operation|null
     * @throws \Exception
     */
    public static function output(
        $operationId,
        $username,
        $sum,
        $sumWithCommission,
        $currency,
        $paymentSystem = 'PMEUR'
    ) {
        $model = new self;
        $model->type = self::TYPE_OUTPUT;
        $model->username = $username;
        $model->sum = $sum;
        $model->sumWithCommission = $sumWithCommission;
        $model->currency = $currency;
        $model->paymentSystem = $paymentSystem;
        $model->transactionId = $operationId;
        return $model->addQueue();
    }

    /**
     * @param $operationId
     * @param $username
     * @param $referral
     * @param $sum
     * @param $percent
     * @param $referralSum
     * @param $currency
     * @param string $paymentSystem
     * @return Operation|null
     * @throws \Exception
     */
    public static function referrals(
        $operationId,
        $username,
        $referral,
        $sum,
        $percent,
        $referralSum,
        $currency,
        $paymentSystem = 'PMEUR'
    ) {
        $model = new self;
        $model->type = self::TYPE_REFERRALS;
        $model->username = $username;
        $model->referral = $referral;
        $model->sum = $sum;
        $model->percent = $percent;
        $model->referralSum = $referralSum;
        $model->currency = $currency;
        $model->paymentSystem = $paymentSystem;
        $model->transactionId = $operationId;
        return $model->addQueue();
    }

    /**
     * @return $this|null
     */
    public function addQueue()
    {
        try {
            if ($this->insert()) {
                return $this;
            } else {
                throw new \BadMethodCallException(VarDumper::dump($this->getErrors()));
            }
        } catch (\Exception $e) {
            Yii::error('Incubator operation queue error: ' . $e->getMessage(), 'incubator/operation');
        }
        return null;
    }

    /**
     * Processes
     *
     * @return boolean
     */
    public function process()
    {
        if (null === \Yii::$app->db->transaction) {
            throw new \LogicException("You shall use DB transaction to call this method");
        }

        $result = null;
        $this->status = self::STATUS_PENDING;

        try {

            switch ($this->type) {
                case (self::TYPE_DEPOSIT):
                    $result = $this->api->deposit($this->username, $this->sum, $this->currency, $this->paymentSystem,
                        $this->transactionId);
                    break;
                case (self::TYPE_OUTPUT):
                    $result = $this->api->output($this->username, $this->sum, $this->sumWithCommission, $this->currency,
                        $this->paymentSystem, $this->transactionId);
                    break;
                case (self::TYPE_REFERRALS):
                    $result = $this->api->referrals($this->username, $this->referral, $this->sum, $this->percent,
                        $this->referralSum, $this->currency, $this->paymentSystem, $this->transactionId);
                    break;
            }

            $this->status = self::STATUS_PROCESSED;
            $this->processedAt = new Exception('NOW()');
            $this->debugData = VarDumper::dumpAsString($result);
        } catch (\Exception $e) {
            $this->debugData = $e->getMessage();
            $this->status = self::STATUS_ERROR;
        }

        $this->updateAttributes(['status', 'debugData', 'processedAt']);
        return $result;
    }

    /**
     * Payment to reserve fund
     * @param DepositEvent $event
     */
    public function handleInvoicePayment($event)
    {
        $invoiceData = $event->invoiceData;
        $depositId = ArrayHelper::getValue($invoiceData, 'deposit_id');
        $sum = ArrayHelper::getValue($invoiceData, 'sum');
        $currency = ArrayHelper::getValue($invoiceData, 'currency'); // default USD
        $paymentSystem = ArrayHelper::getValue($invoiceData, 'payment_system'); // only PMUSD
        $code = ArrayHelper::getValue($invoiceData, 'code');
        $purse = ArrayHelper::getValue($invoiceData, 'purse');

        $pm = \Yii::$app->get('pmUsd');
        $result = $pm->transfer($purse, round($sum, 2), $code, "Перевод в резервный фонд Incubator");
        if (isset($result['ERROR'])) {
            throw new \RuntimeException('PerfectMoney USD transfer error: ' . VarDumper::dumpAsString($result['ERROR']));
        }
    }

}