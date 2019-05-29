<?php

namespace BilliePayment\Components\Payment;

use BilliePayment\Components\Api\ApiArguments;
use Shopware\Models\Attribute\Customer;

/**
 * For a better overview and a clearer separation between controller and business logic.
 * Handles responses of the proviver and takes care of token generation and validation.
 */
class Service
{
    /**
     * Names of differnt billie payment means
     * @var array
     */
    const PAYMENT_MEANS = [
        'billie_payment_after_delivery'
    ];

    /**
     * Validate payment data. If successful return true,
     * otherwise return an array with errorflags and messages.
     *
     * @param array $fields
     * @param array $data
     * @return array|bool
     */
    public function validate(array $fields, array $data)
    {
        $errorMessages = [];
        $errorFlag     = [];

        foreach ($fields as $field) {
            if (!array_key_exists($field, $data) || empty(trim($data[$field]))) {
                $errorFlag[$field] = true;
            }
        }

        if (count($errorFlag)) {
            $errorMessages[] = Shopware()->Snippets()->getNamespace('frontend/account/internalMessages')
                ->get('ErrorFillIn', 'Please fill in all red fields');
            
            return [
                'errorFlag' => $errorFlag,
                'messages'  => $errorMessages
            ];
        }

        return true;
    }

    /**
     * Check if payment id belongs to billie payment
     *
     * @param array $payment
     * @return boolean
     */
    public function isBilliePayment(array $payment)
    {
        // Check by name if $payment is a billie payment
        if (array_key_exists('name', $payment) && in_array($payment['name'], self::PAYMENT_MEANS)) {
            return true;
        }

        // Check by id if $payment is a billie payment
        if (array_key_exists('id', $payment)) {
            // Build filter for payment names
            $filters = [];
            foreach (self::PAYMENT_MEANS as $name) {
                $filters[] = ['property' => 'name', 'value' => $name, 'operator' => 'or'];
            }
            unset($filters[0]['operator']);

            // Query all payments for their ids
            $paymentMean = Shopware()->Models()
                ->getRepository('\Shopware\Models\Payment\Payment')
                ->getActivePaymentsQuery($filters)
                ->getArrayResult();

            return $paymentMean && in_array($payment['id'], array_column($paymentMean, 'id'));
        }

        return false;
    }

    /**
     * Saves addtional payment data like legal form and registration number.
     *
     * @param integer $userId
     * @param string $legalForm
     * @param string $regNumber
     * @return void
     */
    public function saveAdditionalPaymentData($userId, $legalForm, $regNumber)
    {
        // Fetch User
        $models = Shopware()->Container()->get('models');
        $user   = $models->getRepository(Customer::class)->find($userId);

        if ($user) {
            // Save attributes
            $attr = $user->getCustomer()->getDefaultBillingAddress()->getAttribute();
            $attr->setBillieLegalform($legalForm);
            $attr->setBillieRegistrationnumber($regNumber);
            $models->persist($attr);
            $models->flush($attr);
        }
    }

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
