<?php


namespace BilliePayment\Subscriber;


use Enlight\Event\SubscriberInterface;

class Document implements SubscriberInterface
{

    public static function getSubscribedEvents()
    {
        return [
            'Shopware_Components_Document::initTemplateEngine::after' => 'afterInitDocumentTemplate'
        ];
    }

    public function afterInitDocumentTemplate(\Enlight_Hook_HookArgs $args)
    {
        /** @var \Shopware_Components_Document $subject */
        $subject = $args->getSubject();
        $subject->_template->assign('Order', $subject->_order->__toArray());
    }
}
