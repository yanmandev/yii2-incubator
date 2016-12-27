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
use yii\base\Component;
use yii\helpers\ArrayHelper;
use yii\helpers\Json;

class Api extends Component
{
    const BASE_API_URL = 'https://incubator.expert/api';

    const ERROR_AUTH = 'error_auth';
    const ERROR_FINISH_STATUS = 'error_finish_status';
    const ERROR_NO_CHECK = 'error_no_check';


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
     * @return array
     */
    public static function getErrors()
    {
        return [
            self::ERROR_AUTH => 'Error during authorization',
            self::ERROR_FINISH_STATUS => 'Add completed with errors',
            self::ERROR_NO_CHECK => 'Do not check email addresses before data request on adding members',
        ];
    }

    /**
     * Performs api call
     *
     * @param string $action Api script name
     * @param array $params Request parameters
     * @return array|bool
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
        if(is_string($result) && isset($errors[$result])) {
            throw new \Exception('Incubator API error: ' . ArrayHelper::getValue($errors, $result));
        }

        return $result;
    }

    public function registerCheck($pagination = 0)
    {
        $data = [
            'pagination' => $pagination,
        ];

        return ArrayHelper::getValue($this->call('check.php', $data), 'data', null);
    }

    public function registerGetUsers($findEmails, $pagination = 0, $check = 'ok')
    {
        $data = [
            'pagination' => $pagination,
            'find_emails' => $findEmails,
            'check' => $check,
        ];

        return ArrayHelper::getValue($this->call('get_users.php', $data), 'data', null);
    }

    public function registerFinish($status = 'ok')
    {
        $data = [
            'status' => $status,
        ];

        return ArrayHelper::getValue($this->call('finish.php', $data), 'data', null);
    }

    public function deposit($username, $sum, $code, $currency, $paymentSystem)
    {
    }

    public function output($username, $sum, $commission, $code, $currency, $paymentSystem)
    {
    }

    public function referrals($username, $sum, $percent, $referralSum, $code, $currency, $paymentSystem)
    {
    }

    public function transferOut($username, $sum, $code, $currency, $paymentSystem)
    {
    }

    public function transferIn($username, $sum, $code, $currency, $paymentSystem)
    {
    }
}