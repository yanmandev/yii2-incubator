<?php

namespace yanpapayan\incubator\models;

use common\core\Core;
use common\models\UserAccount;
use Yii;
use yii\base\Model;
use yii\helpers\HtmlPurifier;

/**
 * Class RegistrationForm
 * @package yanpapayan\incubator\models
 * @author Ian Kuznetsov <yankuznecov@ya.ru>
 */
class RegistrationForm extends Model
{
    public $accountName;
    public $email;
    public $password;
    public $billingPassword;
    public $sponsor;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [
                [
                    'accountName',
                    'email',
                    'sponsor'
                ],
                'filter',
                'filter' => 'trim'
            ],
            [
                [
                    'accountName',
                    'email',
                    //'sponsor',
                    'password'
                ],
                'required'
            ],
            [
                'accountName',
                'unique',
                'targetClass' => '\common\models\UserAccount',
                'targetAttribute' => 'name',
                'message' => 'This username has already been taken.'
            ],
            ['accountName', 'string', 'min' => 2, 'max' => 255],
            //['accountName', 'match', 'pattern' => '/^[a-zA-Z0-9_-]+$/'],
            ['email', 'email'],
            [
                [
                    'accountName',
                    'email',
                    'password',
                    'sponsor'
                ],
                'string',
                'max' => 255
            ],
            [
                'email',
                'unique',
                'targetClass' => '\common\models\UserAccount',
                'message' => 'This email address has already been taken.'
            ],
            [['password'], 'string', 'min' => 4],
            [['billingPassword'], 'string', 'min' => 4],
            ['sponsor', 'exist', 'targetClass' => UserAccount::className(), 'targetAttribute' => 'name'],
            [
                [
                    'accountName',
                    'email',
                    'password',
                    'sponsor'
                ],
                'filter',
                'filter' => ['yii\helpers\HtmlPurifier', 'process']
            ]
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return parent::attributeLabels();
    }

    /**
     * Signs user up.
     * @param bool|true $runValidation
     * @return UserAccount|null the saved model or null if saving fails
     * @throws \yii\db\Exception
     */
    public function register($runValidation = true)
    {
        if ($runValidation && !$this->validate()) {
            return null;
        }

        $sponsor = null;
        if (isset($this->sponsor)) {
            $sponsor = UserAccount::find()->byName($this->sponsor)->one();
        }

        $dbTransaction = Yii::$app->db->beginTransaction();
        try {
            $user = new UserAccount();
            $user->name = $this->accountName;
            $user->email = $this->email;
            $user->password = $this->password;
            $user->billingPassword = $this->billingPassword;

            $user->referrerId = ($sponsor instanceof UserAccount)
                ? $sponsor->id : Core::ROOT_USER_ID;

            $user->tryInsert(false);

            $profile = $user->profile;
            $profile->firstName = $this->accountName;
            $profile->tryUpdate(false);

            $dbTransaction->commit();

            return $user;
        } catch (\Exception $e) {
            $dbTransaction->rollBack();
            return null;
        }

    }
}
