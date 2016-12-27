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

class Api extends Component
{
    const BASE_API_URL = 'https://incubator.expert/api';

    const ERROR_AUTH = 'ERROR_AUTH';
    const ERROR_FINISH_STATUS = 'ERROR_FINISH_STATUS';
    const ERROR_NO_CHECK = 'ERROR_NO_CHECK';


    /** @var string Login to the back office expert system */
    public $accountName;

    /** @var string The password to access the API */
    public $accountKey;

    protected $httpClient;

    /**
     * @return Client GuzzleHttp client
     */
    public function getHttpClient()
    {
        if (null === $this->httpClient) {
            $this->httpClient = new Client([
                'base_uri' => static::BASE_API_URL
            ]);
        }

        return $this->httpClient;
    }

    public function registerCheck($pagination = 0)
    {

    }

    public function registerGetUsers($findEmails, $pagination = 0)
    {
    }

    public function registerFinish()
    {
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