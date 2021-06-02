<?php

namespace BilliePayment\Subscriber\Frontend;

use Enlight\Event\SubscriberInterface;
use Shopware\Bundle\CookieBundle\CookieCollection;
use Shopware\Bundle\CookieBundle\Structs\CookieGroupStruct;
use Shopware\Bundle\CookieBundle\Structs\CookieStruct;

class CookieSubscriber implements SubscriberInterface
{
    /**
     * @var \Enlight_Components_Snippet_Manager
     */
    private $snippets;

    public function __construct(\Enlight_Components_Snippet_Manager $snippets)
    {
        $this->snippets = $snippets;
    }

    public static function getSubscribedEvents()
    {
        return [
            'CookieCollector_Collect_Cookies' => 'addCookies',
        ];
    }

    public function addCookies()
    {
        $pluginNamespace = $this->snippets->getNamespace('frontend/billie_payment/cookies');

        $collection = new CookieCollection();
        $collection->add(new CookieStruct(
            'billie_payment',
            '/^(ajs_.*|intercom-session-.*|mkjs_.*|fs_uid)$/',
            $pluginNamespace->get('name'),
            CookieGroupStruct::TECHNICAL
        ));

        return $collection;
    }
}
