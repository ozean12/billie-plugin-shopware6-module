<?php

/**
 * Example Demo Payment Prover
 */
class Shopware_Controllers_Frontend_DemoPaymentProvider extends Enlight_Controller_Action
{
    /**
     * Add template dir prior dispatching views.
     */
    public function preDispatch()
    {
        /** @var \Shopware\Components\Plugin $plugin */
        $plugin = $this->get('kernel')->getPlugins()['BilliePayment'];

        $this->get('template')->addTemplateDir($plugin->getPath() . '/Resources/views/');
    }

    /**
     * Show Pay Action -> either "pay" with the provider or cancel it.
     */
    public function payAction()
    {
        $cancelUrl = $this->Request()->getParam('cancelUrl') . $this->getUrlParameters('canceled');
        $returnUrl = $this->Request()->getParam('returnUrl') . $this->getUrlParameters('accepted');

        $this->View()->assign([
            'firstName' => $this->Request()->getParam('firstName'),
            'lastName'  => $this->Request()->getParam('lastName'),
            'amount'    => $this->Request()->getParam('amount'),
            'currency'  => $this->Request()->getParam('currency'),
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl
        ]);
    }

    /**
     * Generate the url parameters for the action.
     *
     * @param string $status Action status
     * @return string
     */

    private function getUrlParameters($status)
    {
        $params = [
            'status'        => $status,
            'token'         => $this->Request()->getParam('token'),
            'signature'     => $this->Request()->getParam('signature'),
            'transactionId' => random_int(0, 1000)
        ];

        return '?' . http_build_query($params);
    }
}
