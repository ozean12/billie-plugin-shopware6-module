<?php

use BilliePayment\Models\Api;
use Shopware\Components\CSRFWhitelistAware;

/**
 * Backend Controller for lightweight backend module.
 * Manages Billie Order Details and States.
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
     * Retrieves the current order status from billie.
     *
     * @return void
     */
    public function indexAction()
    {
        $order = $this->Request()->getParam('order_id');

        // TODO: Retrieve Order (GET /v1/order/{order_id})
        // Test data for now
        $data = [
            'state' => 'canceled',
            'order_id' => $order,
            'bank_account' => [
                'iban' => '12345678910',
                'bic'  => '12345678910'
            ],
            'debtor_company' => [
                'name' => 'John Doe',
                'address_house_number' => 'John Doe',
                'address_house_street' => 'XYZ',
                'address_house_city' => 'ABCDE',
                'address_house_postal_code' => '12345',
                'address_house_country' => 'USA',
            ]
        ];

        $this->View()->assign($data);
    }

    /**
     * Confirms direct payment by merchent.
     *
     * @return void
     */
    public function confirmPaymentAction()
    {
        // TODO: Call POST /v1/order/{order_id}/confirm-payment
        $order  = $this->Request()->getParam('order_id');
        $amount = floatval($this->Request()->getParam('amount'));

        $this->Front()->Plugins()->Json()->setRenderer();
        $this->View()->assign(['success' => true, 'title' => 'Erfolgreich', 'data' => 'response reason']);
    }

    /**
     * Full Cancelation of an order on billie site.
     *
     * @return void
     */
    public function cancelOrderAction()
    {
        // TODO: run POST /v1/order/{order_1}/cancel
        $order  = $this->Request()->getParam('order_id');
        $this->Front()->Plugins()->Json()->setRenderer();
        $this->View()->assign(['success' => true, 'title' => 'Erfolgreich', 'data' => 'Cancelation Response']);
    }

    /**
     * Whitelisted CSRF actions
     *
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return [ 'index' ];
    }
}
