<?php

namespace BilliePayment\Components;

/**
 * Exception for missing documents.
 */
class MissingDocumentsException extends \Billie\Exception\BillieException
{
    /**
     * @var string
     */
    protected $message;

    /**
     * MissingDocumentsException constructor.
     *
     * @param string $message
     */
    public function __construct($message)
    {
        parent::__construct();
        $this->message = $message;
    }

    /**
     * @return string
     */
    public function getBillieCode()
    {
        return 'MISSING_DOCUMENTS';
    }

    /**
     * @return string
     */
    public function getBillieMessage()
    {
        return $this->message;
    }
}
