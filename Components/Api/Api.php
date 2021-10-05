<?php

namespace BilliePayment\Components\Api;

use Billie\Sdk\Exception\BillieException;
use Billie\Sdk\Exception\GatewayException;
use Billie\Sdk\Model\Amount;
use Billie\Sdk\Model\Request\ConfirmPaymentRequestModel;
use Billie\Sdk\Model\Request\GetBankDataRequestModel;
use Billie\Sdk\Model\Request\OrderRequestModel;
use Billie\Sdk\Model\Request\ShipOrderRequestModel;
use Billie\Sdk\Model\Request\UpdateOrderRequestModel;
use Billie\Sdk\Service\Request\CancelOrderRequest;
use Billie\Sdk\Service\Request\ConfirmPaymentRequest;
use Billie\Sdk\Service\Request\GetBankDataRequest;
use Billie\Sdk\Service\Request\GetOrderDetailsRequest;
use Billie\Sdk\Service\Request\ShipOrderRequest;
use Billie\Sdk\Service\Request\UpdateOrderRequest;
use DateTime;
use Monolog\Logger;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;

/**
 * Service Wrapper for billie API sdk
 */
class Api
{
    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var RequestServiceContainer
     */
    private $container;

    public function __construct(
        RequestServiceContainer $container,
        Logger $logger,
        ModelManager $modelManager
    ) {
        $this->container = $container;
        $this->logger = $logger;
        $this->modelManager = $modelManager;
    }

    /**
     * @throws \Billie\Sdk\Exception\BillieException
     *
     * @return \Billie\Sdk\Model\Order
     */
    public function getBillieOrder(Order $shopwareOrder)
    {
        return $this->container->get(GetOrderDetailsRequest::class)
            ->execute(new OrderRequestModel($shopwareOrder->getTransactionId()));
    }

    public function confirmPayment(Order $shopwareOrder, $grossAmount)
    {
        try {
            $this->container->get(ConfirmPaymentRequest::class)
                ->execute(
                    (new ConfirmPaymentRequestModel($shopwareOrder->getTransactionId()))
                        ->setPaidAmount((float) $grossAmount)
                );
            $billieOrder = $this->getBillieOrder($shopwareOrder);
            $this->updateShopwareOrder($shopwareOrder, $billieOrder);

            return $billieOrder;
        } catch (BillieException $e) {
            $this->logError($shopwareOrder, 'PAYMENT_CONFIRM', $e);

            return $e->getBillieCode();
        }
    }

    /**
     * @param string      $invoiceNumber
     * @param string|null $invoiceUrl
     *
     * @return \Billie\Sdk\Model\Order|string|bool errorCode as string in case of an error or the BillieOrder model
     */
    public function shipOrder(Order $shopwareOrder, $invoiceNumber, $invoiceUrl = null)
    {
        try {
            $billieOrder = $this->container->get(ShipOrderRequest::class)->execute(
                (new ShipOrderRequestModel($shopwareOrder->getTransactionId()))
                    ->setInvoiceUrl($invoiceUrl ?: '.')
                    ->setInvoiceNumber((string)$invoiceNumber)
            );
            $this->updateShopwareOrder($shopwareOrder, $billieOrder);

            return $billieOrder;
        } catch (BillieException $e) {
            $this->logError($shopwareOrder, 'SHIP', $e);

            return $e->getBillieCode();
        }
    }

    /**
     * @param float $gross
     * @param float $net
     *
     * @return \Billie\Sdk\Model\Order|string|bool errorCode as string in case of an error or the BillieOrder model
     */
    public function updateAmount(Order $shopwareOrder, $gross, $net)
    {
        try {
            $this->container->get(UpdateOrderRequest::class)->execute(
                (new UpdateOrderRequestModel($shopwareOrder->getTransactionId()))
                    ->setAmount(
                        (new Amount())
                            ->setNet($net)
                            ->setGross($gross)
                    )
            );
            $billieOrder = $this->getBillieOrder($shopwareOrder);
            $this->updateShopwareOrder($shopwareOrder, $billieOrder);

            return $billieOrder;
        } catch (BillieException $e) {
            $this->logError($shopwareOrder, 'UPDATE_AMOUNT', $e);

            return $e->getBillieCode();
        }
    }

    /**
     * @param float $grossAmount
     *
     * @return \Billie\Sdk\Model\Order|string|bool errorCode as string in case of an error, true if the order has been canceled
     */
    public function partlyRefund(Order $shopwareOrder, $grossAmount)
    {
        $billieOrder = $this->getBillieOrder($shopwareOrder);

        if ($grossAmount >= $billieOrder->getAmount()->getGross()) {
            return $this->cancelOrder($shopwareOrder);
        }

        $newAmount = ($billieOrder->getAmount()->getGross() - $grossAmount);
        $taxRate = (round(($billieOrder->getAmount()->getGross() / $billieOrder->getAmount()->getNet()), 2) - 1) * 100;
        try {
            $this->container->get(UpdateOrderRequest::class)->execute(
                (new UpdateOrderRequestModel($shopwareOrder->getTransactionId()))
                    ->setAmount(
                        (new Amount())
                            ->setGross($newAmount)
                            ->setTaxRate($taxRate)
                    )
            );

            $billieOrder = $this->getBillieOrder($shopwareOrder);
            $this->updateShopwareOrder($shopwareOrder, $billieOrder);

            return $billieOrder;
        } catch (BillieException $e) {
            $this->logError($shopwareOrder, 'REFUND', $e);

            return $e->getBillieCode();
        }
    }

    public function updateShopwareOrder(Order $shopwareOrder, \Billie\Sdk\Model\Order $billieOrder = null)
    {
        if ($billieOrder === null) {
            $billieOrder = $this->getBillieOrder($shopwareOrder);
        }

        $orderAttribute = $shopwareOrder->getAttribute();
        if ($orderAttribute === null) {
            $orderAttribute = new \Shopware\Models\Attribute\Order();
            $orderAttribute->setOrder($shopwareOrder);
            $this->modelManager->persist($orderAttribute);
        }

        $bankModel = $this->container->get(GetBankDataRequest::class)->execute(new GetBankDataRequestModel());

        $orderAttribute->setBillieBic($billieOrder->getBankAccount()->getBic());
        $orderAttribute->setBillieIban($billieOrder->getBankAccount()->getIban());
        $orderAttribute->setBillieBank($bankModel->getBankName($billieOrder->getBankAccount()->getBic()));
        $orderAttribute->setBillieReferenceid($billieOrder->getUuid());
        $orderAttribute->setBillieState($billieOrder->getState());
        $orderAttribute->setBillieDuration($billieOrder->getDuration());

        $date = new DateTime();
        $date->modify('+' . $orderAttribute->getBillieDuration() . ' days');
        $orderAttribute->setBillieDurationDate($date->format('d.m.Y'));
        $this->modelManager->flush($orderAttribute);
    }

    /**
     * @return bool|string `true` if successful or `string` with the errorCode
     */
    public function cancelOrder(Order $shopwareOrder)
    {
        try {
            $this->container->get(CancelOrderRequest::class)->execute(
                (new OrderRequestModel($shopwareOrder->getTransactionId()))
            );
            $attribute = $shopwareOrder->getAttribute();
            /* @noinspection NullPointerExceptionInspection */
            $attribute->setBillieState(\Billie\Sdk\Model\Order::STATE_CANCELLED);
            $this->modelManager->flush($attribute);

            return true;
        } catch (BillieException $e) {
            $this->logError($shopwareOrder, 'CANCEL', $e);

            return $e->getBillieCode();
        }
    }

    private function logError(Order $order, $operation, BillieException $exception)
    {
        $context = [
            'code' => $exception->getBillieCode(),
            'orderId' => $order->getId(),
            'referenceId' => $order->getTransactionId(),
            'operation' => $operation
        ];

        if ($exception instanceof GatewayException) {
            $requestData = $exception->getRequestData();
            unset($requestData['client_id'], $requestData['client_secret']); // do not log credentials!
            $context['requestData'] = $requestData;
            $context['responseData'] = $exception->getResponseData();
        }

        $this->logger->error($exception->getMessage(), $context);
    }
}
