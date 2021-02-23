<?php

use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Document\Document;

class Shopware_Controllers_Frontend_BillieInvoice extends Enlight_Controller_Action implements CSRFWhitelistAware
{

    public function invoiceAction()
    {
        $hash = $this->request->getParam('hash');

        $qb = $this->getModelManager()->createQueryBuilder();
        $qb->select('document')
            ->from(Document::class, 'document')
            ->innerJoin('document.order', 'e_order')
            ->innerJoin('document.type', 'type')
            ->where(
                $qb->expr()->eq('document.documentId', ':doc_id'),
                $qb->expr()->eq('document.hash', ':hash'),
                $qb->expr()->eq('e_order.id', ':order_id'),
                $qb->expr()->eq('type.key', ':type')
            )
            ->setParameter('doc_id', $this->request->getParam('documentId'))
            ->setParameter('hash', $hash)
            ->setParameter('order_id', $this->request->getParam('orderId'))
            ->setParameter('type', $this->request->getParam('type'));
        $document = $qb->getQuery()->getOneOrNullResult();

        // Return 404 Error if document was not found.
        if (!$document) {
            $this->Response()->setHttpResponseCode(404);
            return;
        }

        $file = Shopware()->DocPath() . "files/documents/{$hash}.pdf";
        if (!file_exists($file)) {
            $this->Response()->setHttpResponseCode(404);
            return;
        }

        $this->Response()->setHeader('Content-Type', 'application/octet-stream', true);
        $this->Response()->setHeader('Content-Type', 'application/pdf', true);
        $this->Response()->setHeader('Content-Disposition', 'inline; filename = "' . basename($file) . '"');
        $this->Response()->setHeader('Content-Transfer-Encoding', 'binary');
        $this->Response()->setHeader('Content-Length', filesize($file));
        $this->Response()->setHeader('Cache-Control', 'private, max-age=0, must-revalidate');
        $this->Response()->setHeader('Pragma', 'public');
        $this->Response()->setHeader('Accept-Ranges', 'bytes');
        readfile($file);
    }

    public function preDispatch()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
    }

    public function getWhitelistedCSRFActions()
    {
        return ['invoice'];
    }
}
