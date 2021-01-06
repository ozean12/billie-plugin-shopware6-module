<?php

namespace Billie\Sdk\HttpClient;

use Billie\Sdk\Exception\BillieException;
use Billie\Sdk\Exception\InvalidRequestException;
use Billie\Sdk\Exception\NotAllowedException;
use Billie\Sdk\Exception\NotFoundException;
use Billie\Sdk\Exception\UnexpectedServerException;
use Billie\Sdk\Exception\UserNotAuthorizedException;


class BillieClient
{
    const METHOD_POST = 'POST';
    const METHOD_GET = 'GET';
    const METHOD_PUT = 'PUT';
    const METHOD_PATCH = 'PATCH';
//    const METHOD_DELETE = 'DELETE'; // not implemented

    const SANDBOX_BASE_URL = 'https://paella-sandbox.billie.io/api/v1/';
    const PRODUCTION_BASE_URL = 'https://paella.billie.io/api/v1/';

    private $apiBaseUrl;

    /** @var string */
    private $authToken;

    public function __construct($authToken = null, $isSandbox = false)
    {
        $this->authToken = $authToken;
        $this->apiBaseUrl = $isSandbox ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
    }


    /**
     * @param $url
     * @param $data
     * @param string $method
     * @param bool $addAuthorisationHeader
     * @return array
     * @throws BillieException
     */
    public function request($url, $data = [], $method = self::METHOD_GET, $addAuthorisationHeader = true)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiBaseUrl.$url);
        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        $requestHeaders = [
            'Content-Type: application/json; charset=UTF-8',
            'Accept: application/json',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'Connection: keep-alive',
        ];
        if ($addAuthorisationHeader) {
            if($this->authToken === null) {
                throw new \RuntimeException('no auth-token has been provided in constructor');
            }
            $requestHeaders[] = 'Authorization: Bearer ' . $this->authToken;
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

        switch ($method) {
            case self::METHOD_POST:
                curl_setopt($ch, CURLOPT_POST, 1);
                break;
            case self::METHOD_PATCH:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
                break;
            case self::METHOD_PUT:
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                break;
        }

        if(count($data) > 0) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

//        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

//        // the number of milliseconds to wait while trying to connect
//        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $connectionTimeout);
//        // the maximum number of milliseconds to allow cURL functions to execute
//        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $executionTimeout);

        // use tls v1.2
        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);

        $response = curl_exec($ch);
        if($response) {
            $response = json_decode($response, true);
        }
        $errno = curl_errno($ch);
        $curlInfo = curl_getinfo($ch);

        // close connection
        curl_close($ch);


        switch ($curlInfo['http_code']) {
            case 200:
            case 204:
                return $response;
            case 400:
                throw new InvalidRequestException(json_encode($response));
            case 401:
                throw new UserNotAuthorizedException();
            case 403:
                throw new NotAllowedException();
            case 404:
                throw new NotFoundException($url);
            //case 500:
            default:
                throw new UnexpectedServerException(isset($response['message']) ? $response['message']: 'Unknown error', isset($response['error']) ? : null);

        }
    }
}
