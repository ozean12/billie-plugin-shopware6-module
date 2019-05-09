<?php

use BilliePayment\Components\BilliePayment\PaymentResponse;
use BilliePayment\Components\BilliePayment\PaymentService;

class Shopware_Controllers_Frontend_BilliePayment extends Shopware_Controllers_Frontend_Payment
{
    const PAYMENTSTATUSPAID = 12;

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        // Check if the payment method is selected, otherwise return to default controller.
        if ('billie_payment_after_delivery' === $this->getPaymentShortName()) {
            return $this->redirect($this->getReturnUrl());
        }
        
        return $this->redirect(['controller' => 'checkout']);
    }

    /**
     * Complete the order if everything is valid.
     */
    public function returnAction()
    {
        /** @var PaymentService $service */
        $service = $this->container->get('billie_payment.payment_service');
        $user    = $this->getUser();
        $billing = $user['billingaddress'];
        
        /** @var PaymentResponse $response */
        $response  = $service->createPaymentResponse($this->Request());
        $signature = $response->signature;
        $token     = $service->createPaymentToken($this->getAmount(), $billing['customernumber']);

        // If token is invalid, cancel action!
        if (!$service->isValidToken($response, $token)) {
            $this->forward('cancel');
            return;
        }
        
        // Loads basked and verifies if it's still the same.
        try {
            $basket = $this->loadBasketFromSignature($signature);
            $this->verifyBasketSignature($signature, $basket);
        } catch (\Exception $e) {
            // TODO: do error handling like redirecting to error page!
            $this->forward('cancel');
        }
        
        // Check response status and save order when everything went fine.
        switch ($response->status) {
            case 'accepted':
                // TODO: Call API Endpoint to create order -> POST /v1/order
                // TODO: get infos from $user, $basket and from plugin config
                // TODO: Update API order status to either 'declined' or 'created' depending on api return state
                // var_dump($response, $user, $basket, $billing);die;
                // Shopware()->Container()->get('pluginlogger')->info('POST /v1/order');
                Shopware()->Session()->apiOrderState = 'created';

                // TODO: Check for actual api error
                // Shopware()->Session()->apiErrorMessages = ['Example: Something went wrong. Please try again'];
                // $this->redirect(['controller' => 'checkout', 'action' => 'confirm']);
                // break;

                $this->saveOrder(
                    $response->transactionId,
                    $response->token,
                    self::PAYMENTSTATUSPAID
                );
                $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                break;
            default:
                $this->forward('cancel');
                break;
        }
    }

    /**
     * Cancel action method.
     */
    public function cancelAction()
    {
    }
    
    /**
     * Generate the url for the return action.
     *
     * @return string
     */
    private function getReturnUrl()
    {
        /** @var PaymentService $service */
        $service = $this->container->get('billie_payment.payment_service');
        $router  = $this->Front()->Router();
        $user    = $this->getUser();
        $billing = $user['billingaddress'];
        $url     = $router->assemble(['action' => 'return', 'forceSecure' => true]);

        $parameter = [
            'status'        => 'accepted',
            'token'         => $service->createPaymentToken($this->getAmount(), $billing['customernumber']),
            'signature'     => $this->persistBasket(),
            'transactionId' => random_int(0, 1000)
        ];

        return $url . '?' . http_build_query($parameter);
    }
}
