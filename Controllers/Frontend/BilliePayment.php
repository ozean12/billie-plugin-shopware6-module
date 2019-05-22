<?php

use BilliePayment\Components\BilliePayment\PaymentResponse;
use BilliePayment\Components\BilliePayment\PaymentService;
use Shopware\Models\Order\Order;


/**
 * Frontend Controller for Billie.io Payment.
 * Handles the Checkout process with billie.io API.
 *
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
 */
class Shopware_Controllers_Frontend_BilliePayment extends Shopware_Controllers_Frontend_Payment
{
    /**
     * Payment Status Paid Code
     * @var integer
     */
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
        $attrs   = $billing['attributes'];

        /** @var PaymentResponse $response */
        $response  = $service->createPaymentResponse($this->Request());
        $signature = $response->signature;
        $token     = $service->createPaymentToken($this->getAmount(), $billing['customernumber']);

        // Make sure that a legalform is selected, otherwise display error message.
        if (!isset($attrs['billieLegalform']) && !isset($attrs['billie_legalform'])) {
            $this->redirect(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => 'MissingLegalForm']);
            return;
        }

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
            $this->forward('cancel');
        }
        
        // Check response status and save order when everything went fine.
        switch ($response->status) {
            case 'accepted':
                /** @var \BilliePayment\Components\BilliePayment\Api $api */
                $api = $this->container->get('billie_payment.api');
                
                // Call Api for created order
                $apiResp = $api->createOrder($service->createApiArgs($user, $this->getBasket()));

                // Save Order on success
                if ($apiResp['success']) {
                    $orderNumber = $this->saveOrder(
                        $response->transactionId,
                        $response->token,
                        self::PAYMENTSTATUSPAID
                    );

                    /** @var \Shopware\Components\Model\ModelManager $models */
                    $models = Shopware()->Container()->get('models');
                    $repo   = $models->getRepository(Order::class);
                    $order  = $repo->findOneBy(['number' => $orderNumber]);
                    $api->helper->updateLocal($order, $apiResp['local']);
                    
                    // Finish checkout
                    $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                    break;
                }

                // Error messages
                $this->redirect(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => $apiResp['data']]);

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
