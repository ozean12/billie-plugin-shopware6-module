<?php

namespace BilliePayment\Subscriber;

use BilliePayment\Enum\PaymentMethods;
use Enlight\Event\SubscriberInterface;

/**
 * Subscriber to assign api messages to the checkout view
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class Checkout implements SubscriberInterface
{
    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => [
                ['addApiMessagesToView', -1],
            ],
        ];
    }

    /**
     * Add API Messages to the Checkout View.
     *
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function addApiMessagesToView(\Enlight_Event_EventArgs $args)
    {
        /** @var \Shopware\Components\Logger $logger */
        $logger = Shopware()->Container()->get('billie_payment.logger');

        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $view       = $controller->View();

        // Only valid actions.
        if (!in_array($request->getActionName(), ['finish', 'payment', 'confirm'])) {
            return;
        }

        // Error Checking
        if (PaymentMethods::exists($view->sPayment['name'])) {
            $error     = ['code' => null, 'invalid' => false];
            $company   = $view->sUserData['billingaddress']['company'];

            // Display Error if not a company.
            if (!isset($company) || is_null($company)) {
                $error = ['code' => 'OnlyCompaniesAllowed', 'invalid' => true];
            }

            // Pass errors to view.
            $view->assign('invalidInvoiceAddressSnippet', $error['code']);
            $view->assign('invalidInvoiceAddress', $error['invalid']);
        }

        // Get API errors from the session and assign them to the view
        $errorCode = $request->getParam('errorCode');
        if ($errorCode) {
            $view->assign('errorCode', $errorCode);
            $logger->error("API-Error Code [{$errorCode}]");
        }
    }
}
