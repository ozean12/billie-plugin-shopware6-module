<?php

namespace Billie\Exception;

/**
 * Class InvalidRequestException
 *
 * @package Billie\Exception
 * @author Marcel Barten <github@m-barten.de>
 */
class InvalidRequestException extends BillieException
{

    protected $message;
    private $response;

    /**
     * InvalidRequestException constructor.
     *
     * @param string $message
     * @param $response
     */
    public function __construct($message, $response)
    {
        parent::__construct();
        $this->message = $message;
        $this->response = $response;
    }

    /**
     * @return string
     */
    public function getBillieCode()
    {
        return 'INVALID_REQUEST';
    }

    /**
     * @return string
     */
    public function getBillieMessage()
    {
        return $this->message;
    }

    /**
     * @return mixed
     */
    public function getResponse()
    {
        return $this->response;
    }
}
