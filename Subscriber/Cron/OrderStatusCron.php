<?php

namespace BilliePayment\Subscriber\Cron;

use BilliePayment\Components\Api\Api;
use BilliePayment\Enum\PaymentMethods;
use BilliePayment\Helper\DocumentHelper;
use BilliePayment\Services\ConfigService;
use Enlight\Event\SubscriberInterface;
use Monolog\Logger;
use Shopware\Components\Model\ModelManager;
use Shopware\Models\Order\Order;

class OrderStatusCron implements SubscriberInterface
{
    const CRON_ACTION_NAME = 'Shopware_CronJob_BilliePaymentCronJobOrderHistoryWatch';

    /**
     * @var ConfigService
     */
    protected $configService;

    /**
     * @var ModelManager
     */
    protected $modelManager;

    /**
     * @var \Enlight_Components_Db_Adapter_Pdo_Mysql
     */
    protected $db;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var Api
     */
    private $api;

    /**
     * @var DocumentHelper
     */
    private $documentHelper;

    public function __construct(
        ModelManager $modelManager,
        \Enlight_Components_Db_Adapter_Pdo_Mysql $db,
        ConfigService $configService,
        Api $api,
        Logger $logger,
        DocumentHelper $documentHelper
    ) {
        $this->modelManager = $modelManager;
        $this->db = $db;
        $this->configService = $configService;
        $this->logger = $logger;
        $this->api = $api;
        $this->documentHelper = $documentHelper;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            self::CRON_ACTION_NAME => 'watchHistory',
        ];
    }

    public function watchHistory(\Shopware_Components_Cron_CronJob $job)
    {
        if ($this->configService->isWatchHistoryChange() === false) {
            return 'watcher is not enabled in plugin config';
        }

        $orderIds = $this->findOrderIdsToProcess();
        $totalOrders = count($orderIds);
        foreach ($orderIds as $key => $result) {
            /** @var Order|null $order */
            $order = $this->modelManager->find(Order::class, $result['id']);

            if ($order === null) {
                // just skip it without logging
                continue;
            }

            $this->logger->info(
                sprintf('Order history watcher: Processing %d/%d order-id %d ...', $key + 1, $totalOrders, $result['id']),
                [
                    'order_id' => $order->getId(),
                    'order_number' => $order->getNumber(),
                ]
            );

            $logContext = [
                'order_id' => $order->getId(),
                'order_number' => $order->getNumber(),
                'new_status' => $result['order_status_id'],
            ];

            try {
                switch ($result['order_status_id']) {
                    case $this->configService->getOrderStatusForAutoProcessing('ship'):
                        $this->logger->info(sprintf('Activating order #%s', $order->getNumber()), array_merge($logContext));
                        if ($order->getAttribute()->getBillieState() !== \Billie\Sdk\Model\Order::STATE_SHIPPED) {
                            $invoiceNumber = $this->documentHelper->getInvoiceNumberForOrder($order);
                            if ($invoiceNumber) {
                                $response = $this->api->shipOrder(
                                    $order,
                                    $invoiceNumber,
                                    $this->documentHelper->getInvoiceUrlForOrder($order)
                                );

                                if ($response instanceof \Billie\Sdk\Model\Order) {
                                    $this->logger->info(sprintf('Order #%s has been activated', $order->getNumber()), array_merge($logContext));
                                }
                            } else {
                                $this->logger->warning(sprintf('Activating order #%s failed, cause no invoice number is available.', $order->getNumber()), array_merge($logContext));
                            }
                        } else {
                            $this->logger->info(sprintf('Activating order #%s - Already shipped', $order->getNumber()), array_merge($logContext));
                        }
                        break;
                    case $this->configService->getOrderStatusForAutoProcessing('cancel'):
                        $this->logger->info(sprintf('Canceling order #%s', $order->getNumber()), array_merge($logContext));
                        if ($order->getAttribute()->getBillieState() !== \Billie\Sdk\Model\Order::STATE_CANCELLED) {
                            $response = $this->api->cancelOrder($order);
                            if ($response === true) {
                                $this->logger->info(sprintf('Order #%s has been canceled', $order->getNumber()), array_merge($logContext));
                            } else {
                                $this->logger->error(sprintf('Order #%s can not be canceled. Error: %s', $order->getNumber(), $response), array_merge($logContext));
                            }
                        } else {
                            $this->logger->info(sprintf('Canceling order #%s- Already canceled', $order->getNumber()), array_merge($logContext));
                        }
                        break;
                    default:
                        $this->logger->info('Nothing to do - invalid status.', $logContext);
                        continue 2;
                }
            } catch (\Exception $e) {
                $logContext['trace'] = $e->getTraceAsString();
                $this->logger->error('Error during processing order: ' . $e->getMessage(), array_merge($logContext));
            }
        }

        return 'Success';
    }

    /**
     * @throws \Exception
     *
     * @return array
     */
    private function findOrderIdsToProcess()
    {
        $allowedOrderStates = [
            $this->configService->getOrderStatusForAutoProcessing('ship'),
            $this->configService->getOrderStatusForAutoProcessing('cancel'),
        ];

        $allowedOrderStates = array_filter($allowedOrderStates, static function ($status) {
            return is_numeric($status);
        });

        if (count($allowedOrderStates) === 0) {
            return [];
        }

        $paymentMethods = PaymentMethods::getNames();

        $query = $this->db->select()
            ->distinct(true)
            ->from(['history' => 's_order_history'], ['order_status_id'])
            ->joinLeft(['s_order' => 's_order'], 'history.orderID = s_order.id', ['id'])
            ->joinLeft(['payment' => 's_core_paymentmeans'], 's_order.paymentID = payment.id', null)
            ->where('history.change_date >= :changeDate')
            ->where('s_order.status IN (' . implode(',', $allowedOrderStates) . ')')
            ->where("payment.name IN ('" . implode("','", $paymentMethods) . "')")
            ->order('history.change_date ASC')
            ->bind([
                'changeDate' => $this->getLastRunDateTime(),
            ]);

        return $this->db->fetchAll($query);
    }

    private function getLastRunDateTime()
    {
        // get the crontab
        $query = 'SELECT `next`, `interval` FROM s_crontab WHERE `action` = ?';
        $row = $this->db->fetchRow($query, [self::CRON_ACTION_NAME]);

        // calculate the last run of the cron
        if (isset($row['start'])) {
            $date = new \DateTime($row['start']);
        } else {
            $date = new \DateTime();
        }
        $date->sub(new \DateInterval('PT' . $row['interval'] . 'S'));

        return $date->format('Y-m-d H:i:s');
    }
}
