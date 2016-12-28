<?php
/**
 * @author Yan Kuznetsov <yankuznecov@ya.ru>
 * Date: 27.12.2016
 * Time: 11:29
 *
 * @see https://incubator.expert/api/docs/incubatorexpert-api-ru.docx
 */

namespace yanpapayan\incubator;

use Guzzle\Http\Client;
use yanpapayan\incubator\events\DepositEvent;
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class Api extends Component
{
    const BASE_API_URL = 'https://incubator.expert/api';

    const MESSAGE_OK = 'ok';

    const ERROR_AUTH = 'error_auth';
    const ERROR_FINISH_STATUS = 'error_finish_status';
    const ERROR_NO_CHECK = 'error_no_check';
    const ERROR_REGISTER = 'error_register';
    const ERROR_LOGIN = 'error_login';
    const ERROR_EMAIL_1 = 'error_email1';
    const ERROR_EMAIL_2 = 'error_email2';
    const ERROR_REFERRER = 'error_referrer';

    const ERROR_USERNAME = 'error_username';
    const ERROR_SUM = 'error_sum';
    const ERROR_CURRENCY = 'error_currency';
    const ERROR_PERCENT = 'error_percent';
    const ERROR_REFERRAL_SUM = 'error_referral_sum';
    const ERROR_EXIST = 'error_exist';
    const ERROR_CODE = 'error_code';
    const ERROR_PAYMENT_SYSTEM = 'error_payment_system';
    const ERROR_RESERVE_SUM = 'error_reserve_sum';

    const CURRENCY_RUB = 'RUB';
    const CURRENCY_EUR = 'EUR';
    const CURRENCY_USD = 'USD';
    const CURRENCY_UAH = 'UAH';
    const CURRENCY_KZT = 'KZT';
    const CURRENCY_BTC = 'BTC';

    /**
     * @see http://estandards.info/formirovanie-eksportnogo-fajla-s-kursami
     */
    const PAYMENT_SYSTEM_PERFECT_MONEY_USD = 'PMUSD';
    const PAYMENT_SYSTEM_PERFECT_MONEY_EUR = 'PMEUR';
    const PAYMENT_SYSTEM_ADVANCED_CASH_USD = 'ADVCUSD';
    const PAYMENT_SYSTEM_ADVANCED_CASH_EUR = 'ADVCEUR';


    /** @var string Login to the back office expert system */
    public $accountName;

    /** @var string The password to access the API */
    public $accountKey;

    /** @var Client */
    protected $httpClient;


    /**
     * @inheritdoc
     */
    public function init()
    {
        assert(isset($this->accountName));
        assert(isset($this->accountKey));

        if (null === $this->httpClient) {
            $this->httpClient = new Client();
        }
    }

    /**
     * @param $message
     * @return string
     */
    public static function formatResponse($message)
    {
        return '<span>' . $message . '</span>';
    }

    /**
     * @return array
     */
    public static function getErrors()
    {
        return [
            self::ERROR_AUTH => 'Error during authorization',
            self::ERROR_FINISH_STATUS => 'Add completed with errors',
            self::ERROR_NO_CHECK => 'Do not check email addresses before data request on adding members',
            self::ERROR_REGISTER => 'Unsuccessful registration',
            self::ERROR_LOGIN => 'The username of the member already exists in the project',
            self::ERROR_EMAIL_1 => 'The first Email is present in the project, the second Email not passed',
            self::ERROR_EMAIL_2 => 'Both Email are present in the project',
            self::ERROR_REFERRER => 'In the project there is no referrer of the new user',
            self::ERROR_USERNAME => 'Wrong username of the member',
            self::ERROR_CODE => 'Wrong operation code',
            self::ERROR_SUM => 'Wrong sum',
            self::ERROR_PERCENT => 'Wrong percent',
            self::ERROR_REFERRAL_SUM => 'Wrong referral sum',
            self::ERROR_EXIST => 'Operation already exists',
            self::ERROR_RESERVE_SUM => 'Wrong sum of the reserve fund',
            self::ERROR_CURRENCY => 'Wrong currency',
            self::ERROR_PAYMENT_SYSTEM => 'Wrong payment system',
        ];
    }

    /**
     * @return string
     */
    public function getHash()
    {
        return md5($this->accountName . ':' . $this->accountKey);
    }

    /**
     * Performs api call
     *
     * @param string $action Api script name
     * @param array $params Request parameters
     * @return array|string
     * @throws \Exception
     */
    protected function call($action, $params = [])
    {
        $defaults = [
            'account' => $this->accountName,
            'key' => $this->accountKey,
        ];

        $client = $this->httpClient;
        $client->setBaseUrl(self::BASE_API_URL . '/' . $action);
        $request = $client->post(null, [], ArrayHelper::merge($defaults, $params))->send();

        $result = Json::decode($request->getBody(true));
        $errors = self::getErrors();
        if (is_string($result) && isset($errors[$result])) {
            throw new \Exception('Incubator API error: ' . ArrayHelper::getValue($errors, $result));
        }

        return $result;
    }

    /**
     * @param $operationId
     * @param $username
     * @return string
     */
    protected function encodeOperationId($operationId, $username)
    {
        return md5($operationId . ':' . $username);
    }

    /**
     * @param int $pagination
     * @return array|string
     * @throws \Exception
     */
    public function registerCheck($pagination = 0)
    {
        $data = [
            'pagination' => $pagination,
        ];

        return $this->call('check.php', $data);
    }

    /**
     * @param $findEmails
     * @param int $pagination
     * @param string $check
     * @return array|string
     * @throws \Exception
     */
    public function registerGetUsers($findEmails, $pagination = 0, $check = 'ok')
    {
        $data = [
            'pagination' => $pagination,
            'find_emails' => $findEmails,
            'check' => $check,
        ];

        return $this->call('get_users.php', $data);
    }

    /**
     * @param string $status
     * @return array|string
     * @throws \Exception
     */
    public function registerFinish($status = 'ok')
    {
        $data = [
            'status' => $status,
        ];

        return $this->call('finish.php', $data);
    }

    /**
     * @param $username
     * @param $sum
     * @param $currency
     * @param $paymentSystem
     * @param $operationId
     * @return array|string
     * @throws \Exception
     */
    public function deposit($username, $sum, $currency, $paymentSystem, $operationId)
    {
        $data = [
            'code' => $this->encodeOperationId($operationId, $username),
            'username' => $username,
            'sum' => $sum,
            'currency' => $currency,
            'payment_system' => $paymentSystem,
        ];

        $invoice = $this->call('deposit.php', $data);
        if ($invoice) {
            $event = new DepositEvent(['invoiceData' => $invoice]);
            $this->trigger(DepositEvent::EVENT_INVOICE_PAYMENT, $event);
        }

        return $invoice;
    }

    /**
     * @param $username
     * @param $sum
     * @param $sumWithCommission
     * @param $currency
     * @param $paymentSystem
     * @param $operationId
     * @return array|string
     * @throws \Exception
     */
    public function output($username, $sum, $sumWithCommission, $currency, $paymentSystem, $operationId)
    {
        $data = [
            'code' => $this->encodeOperationId($operationId, $username),
            'username' => $username,
            'sum' => $sum,
            'sum_w_commission' => $sumWithCommission,
            'currency' => $currency,
            'payment_system' => $paymentSystem,
        ];

        return $this->call('output.php', $data);
    }

    /**
     * @param $username
     * @param $referral
     * @param $sum
     * @param $percent
     * @param $referralSum
     * @param $currency
     * @param $paymentSystem
     * @param $operationId
     * @return array|string
     * @throws \Exception
     */
    public function referrals(
        $username,
        $referral,
        $sum,
        $percent,
        $referralSum,
        $currency,
        $paymentSystem,
        $operationId
    ) {
        $data = [
            'code' => $this->encodeOperationId($operationId, $username),
            'username' => $username,
            'referral' => $referral,
            'sum' => $sum,
            'percent' => $percent,
            'referralSum' => $referralSum,
            'currency' => $currency,
            'payment_system' => $paymentSystem,
        ];

        return $this->call('referrals.php', $data);
    }

    /**
     * @param $username
     * @param $sum
     * @param $currency
     * @param $paymentSystem
     * @param $operationId
     * @return array|string
     * @throws \Exception
     */
    public function transferOut($username, $sum, $currency, $paymentSystem, $operationId)
    {
        $data = [
            'code' => $this->encodeOperationId($operationId, $username),
            'username' => $username,
            'sum' => $sum,
            'currency' => $currency,
            'payment_system' => $paymentSystem,
        ];

        return $this->call('transferout.php', $data);
    }

    /**
     * @param $username
     * @param $sum
     * @param $currency
     * @param $paymentSystem
     * @param $operationId
     * @return array|string
     * @throws \Exception
     */
    public function transferIn($username, $sum, $currency, $paymentSystem, $operationId)
    {
        $data = [
            'code' => $this->encodeOperationId($operationId, $username),
            'username' => $username,
            'sum' => $sum,
            'currency' => $currency,
            'payment_system' => $paymentSystem,
        ];

        return $this->call('transferin.php', $data);
    }
}