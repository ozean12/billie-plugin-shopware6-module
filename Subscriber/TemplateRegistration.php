<?php

namespace BilliePayment\Subscriber;

use Enlight\Event\SubscriberInterface;

/**
 * Subscriber to register the plugin template directory before dispatch.
 */
class TemplateRegistration implements SubscriberInterface
{
    /**
     * @var string
     */
    private $pluginDirectory;

    /**
     * @var \Enlight_Template_Manager
     */
    private $templateManager;

    /**
     * @param string $pluginDirectory
     * @param \Enlight_Template_Manager $templateManager
     */
    public function __construct($pluginDirectory, \Enlight_Template_Manager $templateManager)
    {
        $this->pluginDirectory = $pluginDirectory;
        $this->templateManager = $templateManager;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            'Enlight_Controller_Action_PreDispatch'                => 'onPreDispatch',
            'Enlight_Controller_Action_PostDispatch_Backend_Index' => 'addMenuItem'
        ];
    }

    /**
     * Add Menu item sprite class
     * @param \Enlight_Event_EventArgs $args
     */
    public function addMenuItem(\Enlight_Event_EventArgs $args)
    {
        /** @var \Enlight_Controller_Action $controller */
        $controller = $args->getSubject();
        $view       = $controller->View();
        
        if ($view->hasTemplate()) {
            $view->extendsTemplate('backend/billie_overview/menuitem.tpl');
        }
    }

    /**
     * Add template dir prior dispatching views.
     */
    public function onPreDispatch()
    {
        $this->templateManager->addTemplateDir($this->pluginDirectory . '/Resources/views');
    }
}
