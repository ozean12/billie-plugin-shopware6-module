<?php


namespace BilliePayment\Helper;


use Shopware\Models\Order\Document\Document;
use Shopware\Models\Order\Order;

class DocumentHelper
{

    public static function getInvoiceUrlForOrder(Order $order)
    {
        /** @var Document $document */
        foreach ($order->getDocuments() as $document) {
            if ($document->getType()->getKey() === 'invoice') {
                return self::getInvoiceUrl($document);
            }
        }
        return null;
    }

    public static function getInvoiceNumberForOrder(Order $order)
    {
        /** @var Document $document */
        foreach ($order->getDocuments() as $document) {
            if ($document->getType()->getKey() === 'invoice') {
                return $document->getDocumentId();
            }
        }
        return null;
    }

    private static function getInvoiceUrl(Document $document)
    {
        return Shopware()->Front()->Router()->assemble([
            'controller' => 'BillieInvoice',
            'action' => 'invoice',
            'module' => 'frontend',
            'type' => $document->getType()->getKey(),
            'orderId' => $document->getOrder()->getId(),
            'documentId' => $document->getDocumentId(),
            'hash' => $document->getHash(),
        ]);
    }

}