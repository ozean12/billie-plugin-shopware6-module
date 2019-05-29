<?php

use Shopware\Components\CSRFWhitelistAware;
use BilliePayment\Components\Payment\Service;

/**
 * Backend Controller for lightweight backend module.
 * Manages Billie Order Details and States.
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration
 * @phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
 */
class Shopware_Controllers_Backend_BillieOverview extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * Assign CSRF-Token to view.
     *
     * @return void
     */
    public function postDispatch()
    {
        $csrfToken = $this->container->get('BackendSession')->offsetGet('X-CSRF-Token');
        $this->View()->assign([ 'csrfToken' => $csrfToken ]);
    }

    /**
     * Index action displays list with orders paid with billie.io
     *
     * @return void
     */
    public function indexAction()
    {
        // Build Filters
        $filters = [];
        foreach (Service::PAYMENT_MEANS as $name) {
            $filters[] = ['property' => 'payment.name', 'value' => $name, 'operator' => 'or'];
        }
        unset($filters[0]['operator']);

        /** @var \BilliePayment\Components\Api\Api $api */
        $api     = $this->container->get('billie_payment.api');
        $sort    = [['property' => 'orders.orderTime', 'direction' => 'DESC']];
        $orders = $api->getList(intval($this->Request()->getParam('page', 1)), 25, $filters, $sort);
        
        // Assign view data
        $this->View()->assign('errorCode', $this->Request()->getParam('errorCode'));
        $this->View()->assign($orders);
        $this->View()->assign([
            'statusClasses' =>  [
                'created'  => 'info',
                'declined' => 'danger',
                'shipped'  => 'success',
                'paid_out' => 'success',
                'late'     => 'warning',
                'complete' => 'success',
                'canceled' => 'danger',
            ]
        ]);
    }

    /**
     * Retrieves the current order status from billie.
     *
     * @return void
     */
    public function orderAction()
    {
        /** @var \BilliePayment\Components\Api\Api $api */
        $api      = $this->container->get('billie_payment.api');
        $order    = $this->Request()->getParam('order_id');
        $response = $api->retrieveOrder($order);

        if ($response['success'] === false) {
            $this->redirect(['controller' => 'BillieOverview', 'action' => 'index', 'errorCode' => $response['data']]);
            return;
        }

        $this->View()->assign($response);
    }

    /**
     * Mark current order as shipped.
     *
     * @return void
     */
    public function shipOrderAction()
    {
        /** @var \BilliePayment\Components\Api\Api $api */
        $api      = $this->container->get('billie_payment.api');
        $order    = $this->Request()->getParam('order_id');
        $response = $api->shipOrder($order);

        $this->Front()->Plugins()->Json()->setRenderer();
        $this->View()->assign($response);
    }

    /**
     * Confirms direct payment by merchent.
     *
     * @return void
     */
    public function confirmPaymentAction()
    {
        /** @var \BilliePayment\Components\Api\Api $api */
        $api      = $this->container->get('billie_payment.api');
        $amount   = floatval($this->Request()->getParam('amount'));
        $order    = $this->Request()->getParam('order_id');
        $response = $api->confirmPayment($order, $amount);

        // Return result message
        $this->Front()->Plugins()->Json()->setRenderer();
        $this->View()->assign($response);
    }

    /**
     * Full Cancelation of an order on billie site.
     *
     * @return void
     */
    public function cancelOrderAction()
    {
        /** @var \BilliePayment\Components\Api\Api $api */
        $api      = $this->container->get('billie_payment.api');
        $order    = $this->Request()->getParam('order_id');
        $response = $api->cancelOrder($order);

        // Return result message
        $this->Front()->Plugins()->Json()->setRenderer();
        $this->View()->assign($response);
    }

    /**
     * Whitelisted CSRF actions
     *
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return [ 'index', 'order' ];
    }
}
