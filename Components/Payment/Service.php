<?php

namespace BilliePayment\Components\Payment;

use BilliePayment\Components\Api\ApiArguments;
use Shopware\Models\Attribute\Customer;
use Shopware\Models\Payment\Payment;

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
     * Validate payment data based on legal form. If successful return true,
     * otherwise return an array with errorflags and messages.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     * @param array $fields
     * @param \Enlight_Controller_Request_Request $request
     * @return array|bool
     */
    public function validate(array $fields, \Enlight_Controller_Request_Request $request)
    {
        // Error Bags
        $errorMessages = [];
        $errorFlag     = [];

        // Check what fields are required based on legal form
        if (\Billie\Util\LegalFormProvider::isVatIdRequired($request->getParam('sBillieLegalForm'))) {
            $fields[] = 'sBillieVatId';
        }

        if (\Billie\Util\LegalFormProvider::isRegistrationIdRequired($request->getParam('sBillieLegalForm'))) {
            $fields[] = 'sBillieRegistrationnumber';
        }

        // validate Fields and return error if there are any
        foreach ($fields as $field) {
            if (!$request->has($field) || empty(trim($request->getParam($field)))) {
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
                ->getRepository(Payment::class)
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
     * @param string|null $vatId
     * @return void
     */
    public function saveAdditionalPaymentData($userId, $legalForm, $regNumber, $vatId=null)
    {
        // Fetch User
        $models = Shopware()->Container()->get('models');
        $user   = $models->getRepository(Customer::class)->find($userId);

        if ($user) {
            // Set vat id
            $billing = $user->getCustomer()->getDefaultBillingAddress();
            if (!is_null($vatId)) {
                $billing->setVatId($vatId);
            }

            // Set attrs
            $attr = $billing->getAttribute();
            $attr->setBillieLegalform($legalForm);
            $attr->setBillieRegistrationnumber($regNumber);

            // Save
            $models->persist($billing);
            $models->persist($attr);
            $models->flush([$billing, $attr]);
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
     * @param string $shortName
     * @return ApiArguments
     */
    public function createApiArgs($user, $basket, $shortName)
    {
        // fix inconsistend camelcase
        $attrs = $user['billingaddress']['attributes'];
        if (array_key_exists('billie_legalform', $attrs) && !array_key_exists('billieLegalform', $attrs)) {
            $attrs['billieLegalform']          = $attrs['billie_legalform'];
            $attrs['billieRegistrationnumber'] = $attrs['billie_registrationnumber'];
        }
        $user['billingaddress']['attributes'] = $attrs;

        // Payment Duration
        $models   = Shopware()->Container()->get('models');
        $payment  = $models->getRepository(Payment::class)->findOneBy(['name' => $shortName]);
        $duration = $payment->getAttribute()->getBillieDuration();

        // Build Args
        $args                = new ApiArguments();
        $args->billing       = $user['billingaddress'];
        $args->amountNet     = $basket['AmountNetNumeric'];
        $args->currency      = $basket['sCurrencyName'];
        $args->taxAmount     = $basket['sAmountTax'];
        $args->customerEmail = $user['additional']['user']['email'];
        $args->country       = $user['additional']['country'];
        $args->duration      = (int) $duration;
        
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
