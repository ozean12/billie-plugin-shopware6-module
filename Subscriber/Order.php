<?php

namespace BilliePayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use BilliePayment\Models\Api;

/**
 * Order Cronjob to check order status.
 */
class Order implements SubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Shopware_CronJob_CheckOrderStatus' => 'onCheckOrderStatus',
        ];
    }

    /**
     * Check order state and notify billie if order is shipped.
     *
     * @param \Shopware_Components_Cron_CronJob $job
     * @return void
     */
    public function onCheckOrderStatus(\Shopware_Components_Cron_CronJob $job)
    {
        // load all billie orders that are in the 'created' state
        $models = Shopware()->Container()->get('models');
        $api    = $models->getRepository(Api::class);
        $rows   = $api->findBy(['state' => 'created']);

        // Check each order if it is shipped, and if so, tell billie about it and update state
        foreach ($rows as $row) {
            $order = $row->getOrder();

            if ('completely_delivered' === $order->getOrderStatus()->getName()) {
                // TODO: run POST /v1/order/{order_id}/ship
                // TODO: Flag billie state as 'shipped' (or declined based on api response)
                $row->setState('shipped');
            }
        }
        $models->flush();
    }
}
