<?php

namespace BilliePayment\Subscriber\Backend;

use Enlight\Event\SubscriberInterface;

class BaseSubscriber implements SubscriberInterface
{
    private $pluginDir;

    public function __construct($pluginDir)
    {
        $this->pluginDir = $pluginDir;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PostDispatch_Backend_Base' => 'extendExtJS',
        ];
    }

    /**
     * Extend Attribute Form to make BIC/IBAN readonly.
     */
    public function extendExtJS(\Enlight_Controller_ActionEventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $view = $controller->View();
        $view->addTemplateDir($this->pluginDir . '/Resources/views/');
        $view->extendsTemplate('backend/billie_payment/Shopware.attribute.Form.js');
    }
}
