<?php
/**
 * @author Ian Kuznetsov <yankuznecov@ya.ru>
 * Date: 27.12.2016
 * Time: 12:56
 */

namespace yanpapayan\incubator\commands;

use common\incubator\models\RegistrationForm;
use common\models\UserAccount;
use yanpapayan\incubator\Api;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use yii\helpers\VarDumper;

/**
 * Class RegistrationCommand
 * @package yanpapayan\incubator\commands
 * @author Ian Kuznetsov <yankuznecov@ya.ru>
 */
class RegistrationCommand extends Controller
{
    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function actionSync()
    {
        /** @var Api $api */
        $api = \Yii::$app->get('incubator');

        $pagination = 0;
        $do = 1;

        while ($do) {

            $checkUsers = $api->registerCheck($pagination);
            if (empty($checkUsers)) {
                $do = 0;
            }

            $findEmails = [];
            foreach ($checkUsers as $item) {
                $id = ArrayHelper::getValue($item, 'id');
                $email = ArrayHelper::getValue($item, 'email');
                $user = UserAccount::find()->byEmail($email)->one();
                if (null !== $user) {
                    $findEmails[] = $id;
                }
            }

            $detailedUsers = $api->registerGetUsers($findEmails, $pagination);
            foreach ($detailedUsers as $user) {
                $referrerName = ArrayHelper::getValue($user, 'ref_name');
                $username = ArrayHelper::getValue($user, 'username');
                $email = ArrayHelper::getValue($user, 'email');
                $password = ArrayHelper::getValue($user, 'password');

                $existUser = UserAccount::find()->byEmail($email)->one();
                if (null !== $existUser) {
                    continue;
                }

                $referrerName = ($referrerName == 'none')
                    ? null
                    : $referrerName;

                // register user
                $model = new RegistrationForm();
                $model->sponsor = $referrerName;
                $model->accountName = $username;
                $model->email = $email;
                $model->password = $password;
                $model->billingPassword = $password;
                if ($account = $model->register()) {
                    $this->stdout("User: {$account->email} created" . PHP_EOL);
                } else {
                    \Yii::error('The user was not registered: ' . VarDumper::dumpAsString($model->getErrors()));
                }
            }

            $pagination++;
        }

        $api->registerFinish();
    }
}