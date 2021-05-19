<?php

namespace BilliePayment\Helper;

use Shopware\Components\Model\ModelManager;
use Shopware\Components\Routing\Context;
use Shopware\Components\Routing\Router;
use Shopware\Models\Order\Document\Document;
use Shopware\Models\Order\Order;
use Shopware\Models\Shop\Shop;

class DocumentHelper
{
    /**
     * @var ModelManager
     */
    private $modelManager;

    /**
     * @var Router
     */
    private $router;

    /**
     * @var \Shopware_Components_Config
     */
    private $config;

    public function __construct(ModelManager $modelManager, Router $router, \Shopware_Components_Config $config)
    {
        $this->modelManager = $modelManager;
        $this->router = $router;
        $this->config = $config;
    }

    public function getInvoiceUrlForOrder(Order $order)
    {
        $external = $order->getAttribute()->getBillieExternalInvoiceUrl();
        if (!empty($external)) {
            return $external;
        }

        /** @var Document $document */
        foreach ($order->getDocuments() as $document) {
            if ($document->getType()->getKey() === 'invoice') {
                return $this->getInvoiceUrl($document);
            }
        }

        return null;
    }

    public function getInvoiceNumberForOrder(Order $order)
    {
        $external = $order->getAttribute()->getBillieExternalInvoiceNumber();
        if (!empty($external)) {
            return $external;
        }

        /** @var Document $document */
        foreach ($order->getDocuments() as $document) {
            if ($document->getType()->getKey() === 'invoice') {
                return $document->getDocumentId();
            }
        }

        return null;
    }

    private function getInvoiceUrl(Document $document)
    {
        $defaultShop = $this->modelManager->getRepository(Shop::class)->getDefault();

        // fetch current context to restore it after generating url
        $oldContext = $this->router->getContext();

        // create default-storefront context
        $this->router->setContext(Context::createFromShop($defaultShop, $this->config));

        // generate url
        $url = $this->router->assemble([
            'controller' => 'BillieInvoice',
            'action' => 'invoice',
            'module' => 'frontend',
            'type' => $document->getType()->getKey(),
            'orderId' => $document->getOrder()->getId(),
            'documentId' => $document->getDocumentId(),
            'hash' => $document->getHash(),
        ]);

        // restore old context
        $this->router->setContext($oldContext);

        return $url;
    }
}
