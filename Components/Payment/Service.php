<?php

namespace BilliePayment\Components\Payment;

use BilliePayment\Components\Api\ApiArguments;

/**
 * For a better overview and a clearer separation between controller and business logic.
 * Handles responses of the proviver and takes care of token generation and validation.
 */
class Service
{
    /**
     * @param \Enlight_Controller_Request_Request $request
     * @return Response
     */
    public function createPaymentResponse(\Enlight_Controller_Request_Request $request)
    {
        $response                = new Response();
        $response->transactionId = $request->getParam('transactionId', null);
        $response->status        = $request->getParam('status', null);
        $response->token         = $request->getParam('token', null);
        $response->signature     = $request->getParam('signature', null);

        return $response;
    }

    /**
     * @param array $user
     * @param array $basket
     * @return ApiArguments
     */
    public function createApiArgs($user, $basket)
    {
        // fix inconsistend camelcase
        $attrs = $user['billingaddress']['attributes'];
        if (array_key_exists('billie_legalform', $attrs) && !array_key_exists('billieLegalform', $attrs)) {
            $attrs['billieLegalform']          = $attrs['billie_legalform'];
            $attrs['billieRegistrationnumber'] = $attrs['billie_registrationnumber'];
        }
        $user['billingaddress']['attributes'] = $attrs;

        $args                = new ApiArguments();
        $args->billing       = $user['billingaddress'];
        $args->amountNet     = $basket['AmountNetNumeric'];
        $args->currency      = $basket['sCurrencyName'];
        $args->taxAmount     = $basket['sAmountTax'];
        $args->customerEmail = $user['additional']['user']['email'];
        $args->country       = $user['additional']['country'];
        $args->duration      = (int) $user['additional']['payment']['attributes']['core']['billie_duration'];
        
        return $args;
    }

    /**
     * @param Response $response
     * @param string $token
     * @return bool
     */
    public function isValidToken(Response $response, $token)
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
