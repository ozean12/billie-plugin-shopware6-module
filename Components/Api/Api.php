<?php

namespace BilliePayment\Components\Api;

use Billie\Sdk\Exception\BillieException;
use Billie\Sdk\Model\Amount;
use Billie\Sdk\Model\Request\ConfirmPaymentRequestModel;
use Billie\Sdk\Model\Request\OrderRequestModel;
use Billie\Sdk\Model\Request\ShipOrderRequestModel;
use Billie\Sdk\Model\Request\UpdateOrderRequestModel;
use Billie\Sdk\Service\Request\CancelOrderRequest;
use Billie\Sdk\Service\Request\ConfirmPaymentRequest;
use Billie\Sdk\Service\Request\GetOrderDetailsRequest;
use Billie\Sdk\Service\Request\ShipOrderRequest;
use Billie\Sdk\Service\Request\UpdateOrderRequest;
use BilliePayment\Services\BankService;
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
     * @var UpdateOrderRequest
     */
    private $updateOrderRequest;

    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var BankService
     */
    private $bankService;

    /**
     * @var GetOrderDetailsRequest
     */
    private $orderDetailsRequest;

    /**
     * @var CancelOrderRequest
     */
    private $cancelOrderRequest;

    /**
     * @var ConfirmPaymentRequest
     */
    private $confirmPaymentRequest;

    /**
     * @var ShipOrderRequest
     */
    private $shipOrderRequest;

    public function __construct(
        Logger $logger,
        ModelManager $modelManager,
        BankService $bankService,
        GetOrderDetailsRequest $orderDetailsRequest,
        UpdateOrderRequest $updateOrderRequest,
        CancelOrderRequest $cancelOrderRequest,
        ConfirmPaymentRequest $confirmPaymentRequest,
        ShipOrderRequest $shipOrderRequest
    ) {
        $this->logger = $logger;
        $this->modelManager = $modelManager;
        $this->updateOrderRequest = $updateOrderRequest;
        $this->bankService = $bankService;
        $this->orderDetailsRequest = $orderDetailsRequest;
        $this->cancelOrderRequest = $cancelOrderRequest;
        $this->confirmPaymentRequest = $confirmPaymentRequest;
        $this->shipOrderRequest = $shipOrderRequest;
    }

    /**
     * @throws BillieException
     *
     * @return \Billie\Sdk\Model\Order
     */
    public function getOrder(Order $order)
    {
        return $this->orderDetailsRequest->execute(new OrderRequestModel($order->getTransactionId()));
    }

    public function confirmPayment(Order $shopwareOrder, $grossAmount)
    {
        try {
            $this->confirmPaymentRequest
                ->execute(
                    (new ConfirmPaymentRequestModel($shopwareOrder->getTransactionId()))
                        ->setPaidAmount((float) $grossAmount)
                );
            $billieOrder = $this->getOrder($shopwareOrder);
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
            $billieOrder = $this->shipOrderRequest->execute(
                (new ShipOrderRequestModel($shopwareOrder->getTransactionId()))
                    ->setInvoiceUrl($invoiceUrl ?: '.')
                    ->setInvoiceNumber($invoiceNumber)
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
            $this->updateOrderRequest->execute(
                (new UpdateOrderRequestModel($shopwareOrder->getTransactionId()))
                    ->setAmount(
                        (new Amount())
                            ->setNet($net)
                            ->setGross($gross)
                    )
            );
            $billieOrder = $this->getOrder($shopwareOrder);
            $this->updateShopwareOrder($shopwareOrder, $billieOrder);

            return $billieOrder;
        } catch (BillieException $e) {
            $this->logError($shopwareOrder, 'UPDATE_AMOUNT', $e);

            return $e->getBillieCode();
        }
    }

    /**
     * @param $grossAmount
     *
     * @return \Billie\Sdk\Model\Order|string|bool errorCode as string in case of an error, true if the order has been canceled
     */
    public function partlyRefund(Order $shopwareOrder, $grossAmount)
    {
        $billieOrder = $this->getOrder($shopwareOrder);

        if ($grossAmount >= $billieOrder->getAmount()->getGross()) {
            return $this->cancelOrder($shopwareOrder);
        }

        $newAmount = ($billieOrder->getAmount()->getGross() - $grossAmount);
        $taxRate = (round(($billieOrder->getAmount()->getGross() / $billieOrder->getAmount()->getNet()), 2) - 1) * 100;
        try {
            $this->updateOrderRequest->execute(
                (new UpdateOrderRequestModel($shopwareOrder->getTransactionId()))
                    ->setAmount(
                        (new Amount())
                            ->setGross($newAmount)
                            ->setTaxRate($taxRate)
                    )
            );

            $billieOrder = $this->getOrder($shopwareOrder);
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
            $billieOrder = $this->getOrder($shopwareOrder);
        }

        $orderAttribute = $shopwareOrder->getAttribute();
        if ($orderAttribute === null) {
            $orderAttribute = new \Shopware\Models\Attribute\Order();
            $orderAttribute->setOrder($shopwareOrder);
            $this->modelManager->persist($orderAttribute);
        }

        $orderAttribute->setBillieBic($billieOrder->getBankAccount()->getBic());
        $orderAttribute->setBillieIban($billieOrder->getBankAccount()->getIban());
        $orderAttribute->setBillieBank($this->bankService->getBankData($billieOrder));
        $orderAttribute->setBillieReferenceid($billieOrder->getUuid());
        $orderAttribute->setBillieState($billieOrder->getState());
        $orderAttribute->setBillieDuration($billieOrder->getDuration());

        $date = new DateTime();
        $date->modify('+' . $orderAttribute->getBillieDuration() . ' days');
        $orderAttribute->setBillieDurationDate($date->format('d.m.Y'));
        $this->modelManager->flush($orderAttribute);
    }

    /**
     * @return bool|string `true` if successfull or `string` with the errorCode
     */
    public function cancelOrder(Order $shopwareOrder)
    {
        try {
            $this->cancelOrderRequest->execute(
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

    private function logError(Order $order, $operation, BillieException $e)
    {
        $this->logger->error($e->getMessage(), [
            'errorCode' => $e->getBillieCode(),
            'orderId' => $order->getId(),
            'referenceId' => $order->getTransactionId(),
            'operation' => $operation,
            'response' => $e->getResponseData(),
            'request' => $e->getRequestData(),
        ]);
    }
}
