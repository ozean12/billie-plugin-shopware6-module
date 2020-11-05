<?php

use Billie\Command\UpdateOrder;
use BilliePayment\Components\Api\Api;
use BilliePayment\Enum\PaymentMethods;
use BilliePayment\Services\AddressService;
use BilliePayment\Services\BankService;
use BilliePayment\Services\ConfigService;
use BilliePayment\Services\SessionService;
use Monolog\Logger;
use Shopware\Components\DependencyInjection\Container;
use Shopware\Models\Country\Country;
use Shopware\Models\Order\Order;
use Shopware\Models\Payment\Payment;
use Symfony\Component\HttpFoundation\JsonResponse;

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
     *
     * @var int
     */
    const PAYMENTSTATUSPAID = 12; // TODO config

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

                    $billieOrder = $this->billieApi->confirmCheckoutSession(
                        $sessionId,
                        $approvedAddress,
                        $paymentMethod,
                        [
                            'net' => $totals['net'] * 100,
                            'tax' => $totals['tax'] * 100,
                        ],
                        $currency
                    );
                } catch (Exception $e) {
                    $this->logger->addCritical($e->getMessage());

                    return $this->redirect(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => '_UnknownError']);
                }
                if ($billieOrder) {
                    if ($this->configService->isOverrideCustomerAddress()) {
                        $this->addressService->updateBillingAddress($billieOrder, $approvedAddress);
                    }

                    $this->addressService->updateSessionAddress($billieOrder, $approvedAddress);

                    $orderNumber = $this->saveOrder(
                        $billieOrder->referenceId,
                        $billieOrder->referenceId,
                        self::PAYMENTSTATUSPAID //TODO replace by config
                    );
                    $repo = $this->getModelManager()->getRepository(Order::class);
                    /** @var Order $order */
                    $order = $repo->findOneBy(['number' => $orderNumber]);

                    // if we set the orderNumber for the billie order, we are not able to mark the order as shipped on the billie gateway.
                    // also see BILLSWPL-21
                    $billieOrder = $this->billieApi->updateOrder(new UpdateOrder($order, $billieOrder->referenceId));

                    // write determined address to shopware order address
                    $billingAddress = $order->getBilling();

                    $bank = $this->bankService->getBankData($order, $billieOrder);
                    $orderAttribute = $order->getAttribute();
                    if ($orderAttribute === null) {
                        $orderAttribute = new \Shopware\Models\Attribute\Order();
                        $orderAttribute->setOrderId($order->getId());
                        $order->setAttribute($orderAttribute);
                    }
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

    public function validateAddressAction()
    {
        $responseArray = [];
        $billingAddress = $this->sessionService->getBillingAddress();

        $params = $this->Request()->getParams();
        $errorCode = null;
        if ($billingAddress && $params['state'] === 'authorized' && is_array($params['debtor_company'])) {
            /** @var Country $country */
            $country = $this->getModelManager()->getRepository(Country::class)
                ->findOneBy(['iso' => $params['debtor_company']['address_country']]);

            if ($country === null) {
                $errorCode = '_SwCountryNotAvailable';
            } else {
                $this->sessionService->setApprovedAddress($params['debtor_company']);
            }
        } else {
            $errorCode = '_UnknownError';
        }

        $responseArray['status'] = $errorCode === null;
        if ($errorCode) {
            $responseArray['redirect'] = $this->Front()->Router()->assemble(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => $errorCode]);
        }
        $response = new JsonResponse($responseArray, 200);
        $response->send();
    }

    /**
     * Cancel action method.
     */
    public function cancelAction()
    {
    }
}
