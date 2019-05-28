<?php

namespace BilliePayment\Subscriber;

use Enlight\Event\SubscriberInterface;
use BilliePayment\Components\Api\Api;
use BilliePayment\Components\Payment\Service;

/**
 * Subscriber to assign api messages to the checkout view
 * @SuppressWarnings(PHPMD.StaticAccess)
 */
class Checkout implements SubscriberInterface
{
    /**
     * @var Api
     */
    private $api;

    /**
     * @var Service
     */
    private $service;

    /**
     * @param Api $api Api
     */
    public function __construct(Api $api, Service $service)
    {
        $this->api     = $api;
        $this->service = $service;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Frontend_Checkout' => [
                // ['preAuthPaymentMethod'],
                ['addApiMessagesToView', -1],
            ],
            'Enlight_Controller_Action_PostDispatchSecure_Frontend_Checkout' => 'onShippingPayment',
            'Enlight_Controller_Action_PreDispatch_Frontend_Address'         => 'extendAddressForm',
            'Enlight_Controller_Action_PreDispatch_Frontend_Register'        => 'extendAddressForm',
            'Shopware_Modules_Admin_SaveRegister_Successful'                 => 'saveRegisterData',
        ];
    }


    /**
     * Save Additional Informations required by billie.
     *
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function onShippingPayment(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $view       = $controller->View();
        $session    = Shopware()->Session();

        // Save additional info needed by billie
        if ($request->getActionName() === 'saveShippingPayment' && $this->service->isBilliePayment(['id' => $request->getParam('sPayment')])) {
            // Validate input
            $validated = $this->service->validate(['sBillieLegalForm'], $request->getParams());

            if ($validated !== true) {
                $session->sErrorFlag     = $validated['errorFlag'];
                $session->sErrorMessages = $validated['messages'];

                return $controller->redirect([
                    'controller'    => 'checkout',
                    'action'        => 'shippingPayment',
                    'sTarget'       => 'controller',
                    'sTargetAction' => 'index',
                ]);
            }

            // Save additional payment info
            $this->service->saveAdditionalPaymentData(
                $session->sUserId,
                $request->getParam('sBillieLegalForm'),
                $request->getParam('sBillieRegistrationnumber')
            );

            return;
        }

        // Assign error messages and legal forms to shipping/payment checkout view
        if ($request->getActionName() === 'shippingPayment') {
            // Default form data
            $addressAttrs                           = $view->sUserData['billingaddress']['attributes'];
            $sFormData                              = $view->sFormData;
            $sFormData['sBillieLegalForm']          = $addressAttrs['billie_legalform'];
            $sFormData['sBillieRegistrationnumber'] = $addressAttrs['billie_registrationnumber'];

            // Assign form data and errors
            $view->assign('sFormData', $sFormData);
            $view->assign('sErrorFlag', $session->sErrorFlag);
            $view->assign('sErrorMessages', $session->sErrorMessages);
            $view->assign('legalForms', \Billie\Util\LegalFormProvider::all());
            
            unset($session->sErrorFlag);
            unset($session->sErrorMessages);
        }
    }

    /**
     * Save Custom Register data
     * 
     * @SuppressWarnings(PHPMD.Superglobals)
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function saveRegisterData(\Enlight_Event_EventArgs $args)
    {
        $data = $_POST['register']['personal']['address']['attribute'];
        $this->service->saveAdditionalPaymentData(
            $args->id,
            $data['billieLegalform'],
            $data['billieRegistrationnumber']
        );
    }

    /**
     * Add Legalforms to address form
     * 
     * @param \Enlight_Event_EventArgs $args
     * @return void
     */
    public function extendAddressForm(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $view       = $controller->View();

        // Only valid actions
        if (!in_array($request->getActionName(), ['ajaxEditor', 'edit', 'create', 'index'])) {
            return;
        }

        $view->assign('legalForms', \Billie\Util\LegalFormProvider::all());
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
        $logger = Shopware()->Container()->get('pluginlogger');

        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $request    = $controller->Request();
        $view       = $controller->View();

        // Only valid actions.
        if (!in_array($request->getActionName(), ['finish', 'payment', 'confirm'])) {
            return;
        }

        // Error Checking
        if ($view->sPayment['name'] === 'billie_payment_after_delivery') {
            $error     = ['code' => null, 'invalid' => false];
            $company   = $view->sUserData['billingaddress']['company'];
            $attrs     = $view->sUserData['billingaddress']['attributes'];
            $legalForm = array_key_exists('billie_legalform', $attrs) ? $attrs['billie_legalform'] : $attrs['billieLegalform'];

            // Display error when legalform is missing.
            if (!isset($legalForm) || is_null($legalForm)) {
                $error = ['code' => 'MissingLegalForm', 'invalid' => true];
            }

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
