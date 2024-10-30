<?php

namespace Invoicebox\V3;

use Invoicebox\Exceptions\ApiNotConfiguredException;

final class Api
{
    const PRODUCTION = 'https://api.invoicebox.ru';
    const STAGING = 'https://api.stage.invbox.ru';

    private static $isConfigured = false;
    private static $baseUrl;
    private static $shopId;
    private static $token;
    private static $userAgent;
    private static $testEnvExists;
    private static $isTestRequest;

    /**
     * @param array $accessConfig
     * @param bool $testEnvExists
     * @param bool $isTestRequest
     * @throws ApiNotConfiguredException
     */
    public static function configure(array $accessConfig, bool $testEnvExists=false, bool $isTestRequest=false)
    {
        if(!isset($accessConfig["shop_id"]) || empty($accessConfig["shop_id"])) throw new ApiNotConfiguredException("shop_id is required param");
        if(!isset($accessConfig["token"]) || empty($accessConfig["token"])) throw new ApiNotConfiguredException("token is required param");
        if(!isset($accessConfig["user_agent"]) || empty($accessConfig["user_agent"])) {
            self::$userAgent = "PHP SDK/" . \Invoicebox\Invoicebox::SDK_VERSION;
        }

        self::$shopId = $accessConfig["shop_id"];
        self::$token = $accessConfig["token"];
        self::$testEnvExists = boolval($testEnvExists);
        self::$isTestRequest = boolval($isTestRequest);
        self::$baseUrl = self::$testEnvExists ? self::STAGING : self::PRODUCTION;
        self::$isConfigured = true;
    }

    /**
     * @return bool
     * @throws ApiNotConfiguredException
     * @throws \Invoicebox\Exceptions\InvalidRequestException
     * @throws \Invoicebox\Exceptions\NotFoundException
     * @throws \Invoicebox\Exceptions\OperationErrorException
     */
    public static function isConfigured()
    {
        return self::$isConfigured;
    }

    /**
     * @param $url
     * @param $args
     * @return mixed
     * @throws ApiNotConfiguredException
     * @throws \Invoicebox\Exceptions\ApiUnauthorizedException
     * @throws \Invoicebox\Exceptions\InvalidRequestException
     * @throws \Invoicebox\Exceptions\NotFoundException
     * @throws \Invoicebox\Exceptions\OperationErrorException
     */
    public static function get($url, $args=[])
    {
        if (!self::$isConfigured) {
            throw new \Invoicebox\Exceptions\ApiNotConfiguredException();
        }

        $requestArgs = self::requestArgs($args);
        $fullUrl = self::$baseUrl . $url . '?' . http_build_query($requestArgs);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $fullUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt( $curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json', 'Accept:application/json', 'User-Agent:'.self::$userAgent, 'Authorization: Bearer ' . self::$token));
        $result = self::executeAndDecode($curl);

        return $result;
    }

    public static function post($url, $args=[])
    {
        if (!self::$isConfigured) {
            throw new \Invoicebox\Exceptions\ApiNotConfiguredException();
        }

        $json = json_encode(self::requestArgs($args));
        //$json = '{"merchantId":"ffffffff-ffff-ffff-ffff-ffffffffffff","description":"\u041e\u043f\u043b\u0430\u0442\u0430 \u0437\u0430\u043a\u0430\u0437\u0430 #169 \u043d\u0430 \u0441\u0430\u0439\u0442\u0435 opencart.loc","merchantOrderId":"169","amount":43.5,"vatAmount":7.25,"currencyId":"RUB","expirationDate":"2022-05-14T05:07:00+00:00","basketItems":[{"sku":"487346","name":"\u0422\u0435\u0441\u0442\u043e\u0432\u044b\u0439 \u0442\u043e\u0432\u0430\u0440 1","measure":"\u0448\u0442","measureCode":"796","quantity":1,"amount":25.5,"amountWoVat":21.25,"totalAmount":25.5,"totalVatAmount":4.25,"vatCode":"RUS_VAT20","type":"commodity","paymentType":"full_prepayment","excise":0},{"sku":"shipping_flat_rate","name":"\u0415\u0434\u0438\u043d\u0430\u044f \u0441\u0442\u0430\u0432\u043a\u0430","measure":"\u0448\u0442","measureCode":"796","quantity":1,"amount":18,"amountWoVat":15,"totalAmount":18,"totalVatAmount":3,"vatCode":"RUS_VAT20","type":"service","paymentType":"full_prepayment"}],"customer":{"type":"private","name":"Test","phone":"79219770073","email":"vera.develop@yandex.ru"},"languageId":"ru","notificationUrl":"http:\/\/opencart.loc\/index.php?route=extension\/payment\/invoicebox\/callback","successUrl":"http:\/\/opencart.loc\/index.php?route=checkout\/success","returnUrl":"http:\/\/opencart.loc\/index.php?route=checkout\/success","invoiceSetting":{"customerLocked":false}}';

        // fwrite(STDERR, print_r($fullArgs, true));
        $fullUrl = self::$baseUrl . $url;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_URL, $fullUrl);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
            'Accept:application/json',
            'User-Agent:'.self::$userAgent,
            'Authorization: Bearer ' . self::$token
        ));
        $result = self::executeAndDecode($curl);
        return $result;
    }

    public static function delete($url)
    {
        if (!self::$isConfigured) {
            throw new \Invoicebox\Exceptions\ApiNotConfiguredException();
        }

        $fullUrl = self::$baseUrl . $url;

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $fullUrl);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "DELETE");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Accept:application/json',
            'User-Agent:'.self::$userAgent,
            'Authorization: Bearer ' . self::$token
        ));
        $result = self::executeAndDecode($curl);
        return $result;
    }

    public static function put($url, $args)
    {
        if (!self::$isConfigured) {
            throw new \Invoicebox\Exceptions\ApiNotConfiguredException();
        }

        $fullUrl = self::$baseUrl . $url;
        $json = json_encode(self::requestArgs($args));


        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $fullUrl);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $json);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($json),
            'Accept:application/json',
            'User-Agent:'.self::$userAgent,
            'Authorization: Bearer ' . self::$token
        ));
        $result = self::executeAndDecode($curl);
        return $result;
    }

    private static function requestArgs($args)
    {
        if(!is_array($args)) return [];
        return $args;
    }

    private static function executeAndDecode($curl)
    {
        $response = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);

        if(is_string($response)) $response = json_decode($response, true);
        if(is_array($response) && isset($response["error"])) $code = $response["error"]["message"];
        else $code = "";

        if ($status == 401)
            throw new \Invoicebox\Exceptions\ApiUnauthorizedException($code);
        else if ($status == 404)
            throw new \Invoicebox\Exceptions\NotFoundException($code);
        else if ($status == 400)
            throw new \Invoicebox\Exceptions\InvalidRequestException($code);
        else if ($status != 200)
            throw new \Invoicebox\Exceptions\OperationErrorException($code);

        // fwrite(STDERR, print_r($result, true));

        return $response;
    }
}