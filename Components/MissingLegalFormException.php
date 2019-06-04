<?php

namespace BilliePayment\Components;

/**
 * Exception for missing documents.
 */
class MissingLegalFormException extends \Exception
{
    /**
     * @var array
     */
    protected $codes;

    /**
     * MissingLegalFormException constructor.
     *
     * @param array $codes
     */
    public function __construct($codes)
    {
        parent::__construct();
        $this->codes = $codes;
    }

    /**
     * @return array
     */
    public function getErrorCodes()
    {
        return $this->codes;
    }
}
