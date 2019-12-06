<?php


namespace BilliePayment\Services\Logger;


use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * @deprecated unknown
 */
class FileLogger extends Logger
{

    const FILENAME = 'billie-payment.log';

    public function __construct($logDir)
    {
        parent::__construct('ratepay', [], []);
        $this->pushHandler(new RotatingFileHandler($logDir . '/' . self::FILENAME));
    }
}
