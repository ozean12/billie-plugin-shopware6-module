<?php

use BilliePayment\Components\Payment\Response;
use BilliePayment\Components\Payment\Service;
use BilliePayment\Enum\PaymentMethods;
use Shopware\Models\Order\Order;
use Shopware\Models\Payment\Payment;

/**
 * Frontend Controller for Billie.io Payment.
 * Handles the Checkout process with billie.io API.
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration
 * @phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
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
        if (PaymentMethods::exists($this->getPaymentShortName())) {
            return $this->redirect($this->getReturnUrl());
        }

        return $this->redirect(['controller' => 'checkout']);
    }

    /**
     * Complete the order if everything is valid.
     */
    public function returnAction()
    {
        /** @var Service $service */
        $service = $this->container->get('billie_payment.payment_service');
        $user    = $this->getUser();
        $billing = $user['billingaddress'];
        $attrs   = $billing['attributes'];

        /** @var Response $response */
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
        if ($response->status === 'accepted') {
            /** @var \BilliePayment\Components\Api\Api $api */
            $api = $this->container->get('billie_payment.api');
            $session = $this->container->get('session');

            /** @var \Shopware\Components\Model\ModelManager $models */
            $models = Shopware()->Container()->get('models');

            // Call Api for created order
            $apiResp = $api->createOrder(
                $service->createApiArgs($user, $this->getBasket(), $this->getPaymentShortName())
            );

            // Update Email templates
            $payment  = $models->getRepository(Payment::class)->findOneBy(['name' => $this->getPaymentShortName()]);
            $duration = $payment->getAttribute()->getBillieDuration();
            $date = new \DateTime();
            $date->add(new \DateInterval('P' . $duration . 'D'));
            $apiResp['local']['duration'] = $duration;
            $apiResp['local']['duration_date'] = $date->format('d.m.Y');
            $session['billie_api_response'] = $apiResp;

            // Save Order on success
            if ($apiResp['success']) {
                $orderNumber = $this->saveOrder(
                    $response->transactionId,
                    $response->token,
                    self::PAYMENTSTATUSPAID
                );

                $repo   = $models->getRepository(Order::class);
                $order  = $repo->findOneBy(['number' => $orderNumber]);
                $api->helper->updateLocal($order, $apiResp['local']);

                // Finish checkout
                $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                return;
            }

            // Error messages
            $this->redirect(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => $apiResp['data']]);
            return;
        }

        $this->forward('cancel');
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
        /** @var Service $service */
        $service = $this->container->get('billie_payment.payment_service');
        $router  = $this->Front()->Router();
        $user    = $this->getUser();
        $billing = $user['billingaddress'];
        $url     = $router->assemble(['action' => 'return', 'forceSecure' => true]);

        $parameter = [
            'status'        => 'accepted',
            'token'         => $service->createPaymentToken($this->getAmount(), $billing['customernumber']),
            'signature'     => $this->persistBasket(),
            'transactionId' => bin2hex(random_bytes(16))
        ];

        return $url . '?' . http_build_query($parameter);
    }
}
