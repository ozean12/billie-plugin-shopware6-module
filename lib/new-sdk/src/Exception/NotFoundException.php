<?php


namespace Billie\Sdk\Exception;


use Throwable;

class NotFoundException extends BillieException
{

    public function __construct($url, Throwable $throwable = null)
    {
        parent::__construct('Not found: '.$url, null, $throwable);
    }

}