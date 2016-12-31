<?php
/**
 * @author Ian Kuznetsov <yankuznecov@ya.ru>
 * Date: 27.12.2016
 * Time: 12:56
 */


namespace yanpapayan\incubator\actions;

use common\incubator\models\RegistrationForm;
use common\models\UserAccount;
use yanpapayan\incubator\Api;
use yanpapayan\incubator\FormattedException;
use yii\base\Action;


/**
 * Class PostRegistrationAction
 * @package yanpapayan\incubator\actions
 * @author Ian Kuznetsov <yankuznecov@ya.ru>
 */
class PostRegistrationAction extends Action
{
    /** @var  Api */
    protected $api;

    /**
     * @inheritdoc
     */
    public function init()
    {
        $this->api = \Yii::$app->get('incubator');
        parent::init();
    }

    /**
     * @return $this
     * @throws \Exception
     */
    public function run()
    {
        $hash = \Yii::$app->request->post('hash');
        $referrerName = \Yii::$app->request->post('referrer_name');
        $username = \Yii::$app->request->post('username');
        $email = \Yii::$app->request->post('email');
        $emailSecond = \Yii::$app->request->post('email_second');
        $password = \Yii::$app->request->post('password');

        try {

            $apiHash = $this->api->getHash();
            if ($hash !== $apiHash) {
                throw new FormattedException(Api::ERROR_AUTH);
            }

            // check login
            $existLogin = UserAccount::find()->byName($username)->one();
            if ($existLogin !== null) {
                throw new FormattedException(Api::ERROR_LOGIN);
            }

            // check first email
            $existEmail = UserAccount::find()->byEmail($email)->one();
            if ($existEmail !== null) {
                // check second email
                $email = $emailSecond;
                if (empty($email)) {
                    throw new FormattedException(Api::ERROR_EMAIL_1);
                }
                $existEmailSecond = UserAccount::find()->byEmail($email)->one();
                if ($existEmailSecond !== null) {
                    throw new FormattedException(Api::ERROR_EMAIL_2);
                }
            }

            // register user
            $model = new RegistrationForm();
            $model->sponsor = $referrerName;
            $model->accountName = $username;
            $model->email = $email;
            $model->password = $password;
            $model->billingPassword = $password;
            if ($model->register()) {
                return Api::formatResponse(Api::MESSAGE_OK);
            } else {
                throw new FormattedException(Api::ERROR_REGISTER);
            }

        } catch (FormattedException $e) {
            return $e->getMessage();
        } catch (\Exception $e) {
            return Api::formatResponse(Api::ERROR_REGISTER);
        }
    }
}