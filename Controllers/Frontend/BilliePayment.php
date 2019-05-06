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
            return $this->redirect(['action' => 'direct', 'forceSecure' => true]);
        }
        
        return $this->redirect(['controller' => 'checkout']);
    }

    /**
     * iFrame gateway so the customer does not leave the shop store front.
     */
    public function gatewayAction()
    {
        $providerUrl = $this->getProviderUrl();
        $this->View()->assign('gatewayUrl', $providerUrl . $this->getUrlParameters());
    }

    /**
     * direct forewarding
    */
    public function directAction()
    {
        $providerUrl = $this->getProviderUrl();
        $this->redirect($providerUrl . $this->getUrlParameters());
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
     * Generate the url parameters for the action.
     *
     * @return string
     */
    private function getUrlParameters()
    {
        /** @var PaymentService $service */
        $service = $this->container->get('billie_payment.payment_service');
        $router  = $this->Front()->Router();
        $user    = $this->getUser();
        $billing = $user['billingaddress'];

        $parameter = [
            'amount'    => $this->getAmount(),
            'currency'  => $this->getCurrencyShortName(),
            'firstName' => $billing['firstname'],
            'lastName'  => $billing['lastname'],
            'returnUrl' => $router->assemble(['action' => 'return', 'forceSecure' => true]),
            'cancelUrl' => $router->assemble(['action' => 'cancel', 'forceSecure' => true]),
            'signature' => $this->persistBasket(), // signature based on basket and customerID
            'token'     => $service->createPaymentToken($this->getAmount(), $billing['customernumber'])
        ];

        return '?' . http_build_query($parameter);
    }

    /**
     * Returns the URL of the payment provider. This has to be replaced with the real payment provider URL.
     *
     * @return string
     */
    protected function getProviderUrl()
    {
        return $this->Front()->Router()->assemble(['controller' => 'DemoPaymentProvider', 'action' => 'pay']);
    }
}
