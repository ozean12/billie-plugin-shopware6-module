<?php

namespace BilliePayment\Components\BilliePayment;

/**
 * For a better overview and a clearer separation between controller and business logic.
 * Handles responses of the proviver and takes care of token generation and validation.
 */
class PaymentService
{
    /**
     * @param $request \Enlight_Controller_Request_Request
     * @return PaymentResponse
     */
    public function createPaymentResponse(\Enlight_Controller_Request_Request $request)
    {
        $response                = new PaymentResponse();
        $response->transactionId = $request->getParam('transactionId', null);
        $response->status        = $request->getParam('status', null);
        $response->token         = $request->getParam('token', null);
        $response->signature     = $request->getParam('signature', null);

        return $response;
    }

    /**
     * @param PaymentResponse $response
     * @param string $token
     * @return bool
     */
    public function isValidToken(PaymentResponse $response, $token)
    {
        return hash_equals($token, $response->token);
    }

    /**
     * @param float $amount
     * @param int $customerId
     * @return string
     */
    public function createPaymentToken($amount, $customerId)
    {
        return md5(implode('|', [$amount, $customerId]));
    }
}
