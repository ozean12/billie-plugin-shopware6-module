<?php

namespace BilliePayment\Services;

use Billie\Sdk\Model\Request\GetBankDataRequestModel;
use Billie\Sdk\Service\Request\GetBankDataRequest;

class BankService
{
    /**
     * @var GetBankDataRequest
     */
    private $bankDataRequest;

    public function __construct(GetBankDataRequest $bankDataRequest)
    {
        $this->bankDataRequest = $bankDataRequest;
    }

    public function getBankData(\Billie\Sdk\Model\Order $billieOrder)
    {
        $response = $this->bankDataRequest->execute(new GetBankDataRequestModel());
        return $response->getBankName($billieOrder->getBankAccount()->getBic());
    }
}
