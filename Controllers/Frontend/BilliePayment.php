<?php

use Billie\Sdk\Exception\BillieException;
use Billie\Sdk\Model\Address;
use Billie\Sdk\Model\DebtorCompany;
use Billie\Sdk\Model\Request\CheckoutSessionConfirmRequestModel;
use Billie\Sdk\Service\Request\CheckoutSessionConfirmRequest;
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

    /**
     * @var CheckoutSessionConfirmRequest
     */
    private $checkoutSessionConfirmService;

    public function setContainer(Container $loader = null)
    {
        parent::setContainer($loader);
        $this->configService = $this->container->get(ConfigService::class);
        $this->addressService = $this->container->get(AddressService::class);
        $this->sessionService = $this->container->get(SessionService::class);
        $this->bankService = $this->container->get(BankService::class);
        $this->logger = $this->container->get('billie_payment.logger');
        $this->checkoutSessionConfirmService = $this->container->get(CheckoutSessionConfirmRequest::class);
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

            if ($sessionId !== null) {
                try {
                    $sessionDebtorCompany = $this->sessionService->getDebtorCompany();
                    $sessionShippingAddress = $this->sessionService->getShippingAddress();
                    $billieOrder = $this->checkoutSessionConfirmService->execute(
                        (new CheckoutSessionConfirmRequestModel())
                            ->setSessionUuid($sessionId)
                            ->setCompany($sessionDebtorCompany)
                            ->setAmount($this->sessionService->getTotalAmount())
                            ->setDuration($paymentMethod->getAttribute()->getBillieDuration())
                            ->setDeliveryAddress($sessionShippingAddress)
                    );

                    $orderNumber = $this->saveOrder(
                        $billieOrder->getUuid(),
                        $billieOrder->getUuid(),
                        self::PAYMENTSTATUSPAID //TODO replace by config
                    );

                    if ($this->configService->isOverrideCustomerAddress()) {
                        $this->addressService->updateBillingAddress($sessionDebtorCompany);
                        $this->addressService->updateShippingAddress($sessionShippingAddress);
                    }

                    $repo = $this->getModelManager()->getRepository(Order::class);
                    /** @var Order $order */
                    $order = $repo->findOneBy(['number' => $orderNumber]);

                    // if we set the orderNumber for the billie order, we are not able to mark the order as shipped on the billie gateway.
                    // also see BILLSWPL-21
                    //$billieOrder->orderId = $orderNumber;
                    //$this->billieApi->updateOrder($order, $billieOrder);

                    $this->container->get(Api::class)->updateShopwareOrder($order, $billieOrder);

                    // remove all billie payment data from session
                    $this->sessionService->clearData();
                    $this->redirect(['controller' => 'checkout', 'action' => 'finish']);
                } catch (BillieException $e) {
                    $this->logger->error($e->getMessage());
                    $this->redirect(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => '_UnknownError']);
                }

                return;
            }
        }
        $this->redirect(['controller' => 'checkout', 'action' => 'confirm', 'errorCode' => '_UnknownError']);
    }

    public function validateAddressAction()
    {
        Shopware()->Plugins()->Controller()->ViewRenderer()->setNoRender();

        $responseArray = [];

        $params = $this->Request()->getParams();
        $errorCode = null;
        if ($params['state'] === 'authorized') {
            if (is_array($params['debtor_company'])) {
                /** @var Country $country */
                $country = $this->getModelManager()->getRepository(Country::class)
                    ->findOneBy(['iso' => $params['debtor_company']['address_country']]);

                if ($country === null) {
                    $errorCode = '_SwCountryNotAvailable';
                } else {
                    $this->sessionService->updateBillingAddress(new DebtorCompany($params['debtor_company']));
                }
            } else {
                $errorCode = '_UnknownError';
            }

            if (is_array($params['delivery_address'])) {
                /** @var Country $country */
                $country = $this->getModelManager()->getRepository(Country::class)
                    ->findOneBy(['iso' => $params['delivery_address']['country']]);

                if ($country === null) {
                    $errorCode = '_SwCountryNotAvailable';
                } else {
                    $this->sessionService->updateShippingAddress(new Address($params['delivery_address']));
                }
            } else {
                $errorCode = '_UnknownError';
            }
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
