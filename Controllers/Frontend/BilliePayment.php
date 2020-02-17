<?php

use BilliePayment\Components\Api\Api;
use BilliePayment\Components\Api\CommandFactory;
use BilliePayment\Components\Payment\Response;
use BilliePayment\Components\Payment\Service;
use BilliePayment\Enum\PaymentMethods;
use BilliePayment\Services\AddressService;
use BilliePayment\Services\BankService;
use BilliePayment\Services\ConfigService;
use BilliePayment\Services\SessionService;
use Monolog\Logger;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Country\Country;
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
     * @var Api
     */
    private $billieApi;
    /**
     * @var SessionService
     */
    private $sessionService;
    /**
     * @var Logger
     */
    private $logger;
    /**
     * @var ConfigService
     */
    private $configService;
    /**
     * @var AddressService
     */
    private $addressService;
    /**
     * @var BankService
     */
    private $bankService;

    public function setContainer(Container $loader = null)
    {
        parent::setContainer($loader);
        $this->configService = $this->container->get(ConfigService::class);
        $this->addressService = $this->container->get(AddressService::class);
        $this->sessionService = $this->container->get(SessionService::class);
        $this->bankService = $this->container->get(BankService::class);
        $this->billieApi = $this->container->get('billie_payment.api');
        $this->logger = $this->container->get('billie_payment.logger');

    }

    /**
     * Payment Status Paid Code
     * @var integer
     */
    const PAYMENTSTATUSPAID = 12; // TODO config

    /**
     * Index action method.
     *
     * Forwards to the correct action.
     */
    public function indexAction()
    {
        // Check if the payment method is selected, otherwise return to default controller.
        if (PaymentMethods::exists($this->getPaymentShortName())) {
            /** @var Payment $paymentMethod */
            $paymentMethod = $this->getModelManager()->getRepository(Payment::class)->findOneBy(['name' => $this->getPaymentShortName()]);
            $sessionId = $this->sessionService->getCheckoutSessionId(false);
            $approvedAddress = $this->sessionService->getApprovedAddress();
            if ($sessionId !== null && $approvedAddress !== null) {
                try {
                    $totals = $this->sessionService->getTotalAmount();
                    $currency = $this->sessionService->getSession()->offsetGet('sOrderVariables')['sBasket']['sCurrencyName'];

                    $orderId = $this->billieApi->confirmCheckoutSession(
                        $sessionId,
                        $approvedAddress,
                        $paymentMethod,
                        [
                            'net' => $totals['net'] * 100,
                            'tax' => $totals['tax'] * 100
                        ],
                        $currency
                    );

                } catch (Exception $e) {
                    $this->logger->addCritical($e->getMessage());
                    return $this->redirect(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => '_UnknownError']);
                }
                if ($orderId) {
                    $billieOrder = $this->billieApi->getClient()->getOrder($orderId);
                    if($this->configService->isOverrideCustomerAddress()) {
                        $this->addressService->updateBillingAddress($billieOrder->debtorCompany);
                    }

                    $this->addressService->updateSessionAddress($billieOrder->debtorCompany);

                    $orderNumber = $this->saveOrder(
                        $orderId,
                        $orderId,
                        self::PAYMENTSTATUSPAID //TODO replace by config
                    );

                    $this->billieApi->updateOrder($orderId, ['order_id' => $orderNumber]);

                    $repo = $this->getModelManager()->getRepository(Order::class);
                    /** @var Order $order */
                    $order = $repo->findOneBy(['number' => $orderNumber]);

                    // write determined address to shopware order address
                    $billingAddress = $order->getBilling();

                    $bank = $this->bankService->getBankData($order, $billieOrder);
                    $orderAttribute = $order->getAttribute();
                    $orderAttribute->setBillieBic($billieOrder->bankAccount->bic);
                    $orderAttribute->setBillieIban($billieOrder->bankAccount->iban);
                    $orderAttribute->setBillieBank($bank ? $bank['name'] : null);
                    $orderAttribute->setBillieReferenceid($billieOrder->referenceId);
                    $orderAttribute->setBillieState($billieOrder->state);
                    $orderAttribute->setBillieDuration($paymentMethod->getAttribute()->getBillieDuration());
                    $date = new DateTime();
                    $date->modify('+' . $orderAttribute->getBillieDuration() . ' days');
                    $orderAttribute->setBillieDurationDate($date->format('d.m.Y'));

                    $this->getModelManager()->flush([$orderAttribute, $billingAddress]);

                    // remove all billie payment data from session
                    $this->sessionService->clearData();

                    return $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                }
            }
        }

        return $this->redirect(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => '_UnknownError']);
    }

    /**
     * Complete the order if everything is valid.
     */
    public function returnAction()
    {
        /** @var Service $service */
        $service = $this->container->get('billie_payment.payment_service');
        $user = $this->getUser();
        $billing = $user['billingaddress'];
        $attrs = $billing['attributes'];

        /** @var Response $response */
        $response = $service->createPaymentResponse($this->Request());
        $signature = $response->signature;
        $token = $service->createPaymentToken($this->getAmount(), $billing['customernumber']);

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
        } catch (Exception $e) {
            $this->forward('cancel');
        }

        // Check response status and save order when everything went fine.
        if ($response->status === 'accepted') {
            /** @var Api $api */
            $api = $this->container->get('billie_payment.api');
            $session = $this->container->get('session');

            /** @var ModelManager $models */
            $models = Shopware()->Container()->get('models');

            // Call Api for created order
            $apiResp = $api->createOrder(
                $service->createApiArgs($user, $this->getBasket(), $this->getPaymentShortName())
            );

            // Update Email templates
            $payment = $models->getRepository(Payment::class)->findOneBy(['name' => $this->getPaymentShortName()]);
            $duration = $payment->getAttribute()->getBillieDuration();
            $date = new DateTime();
            $date->add(new DateInterval('P' . $duration . 'D'));
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

                $repo = $models->getRepository(Order::class);
                $order = $repo->findOneBy(['number' => $orderNumber]);
                $api->helper->updateLocal($order, $apiResp['local']);

                // Finish checkout
                $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                return;
            }

            $this->redirect(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => $apiResp['data']]);
            return;
        }

        $this->forward('cancel');
    }

    public function validateAddressAction()
    {
        $response = [];
        $billingAddress = $this->sessionService->getBillingAddress();

        $params = $this->Request()->getParams();
        $errorCode = null;
        if ($billingAddress && $params['state'] === 'authorized' && is_array($params['debtor_company'])) {
            /** @var Country $country */
            $country = $this->getModelManager()->getRepository(Country::class)
                ->findOneBy(['iso' => $params['debtor_company']['address_country']]);

            if($country === null) {
                $errorCode = '_SwCountryNotAvailable';
            } else {
                $this->sessionService->setApprovedAddress($params['debtor_company']);
            }
        } else {
            $errorCode = '_UnknownError';
        }

        $response['status'] = $errorCode === null;
        if ($errorCode) {
            $response['redirect'] = $this->Front()->Router()->assemble(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => $errorCode]);
        }
        $this->Response()->setStatusCode(200, 'OK');
        $this->Response()->setHeader('Content-Typ', 'application/json');
        $this->Response()->sendHeaders();
        echo json_encode($response);
        exit;
    }

    /**
     * Cancel action method.
     */
    public function cancelAction()
    {
    }

}
