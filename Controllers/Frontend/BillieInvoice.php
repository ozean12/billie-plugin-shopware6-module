<?php

use Shopware\Components\CSRFWhitelistAware;
use Shopware\Models\Order\Document\Document;

/**
 * Controller to send invoice pdf if authenticated.
 *
 * @phpcs:disable PSR1.Classes.ClassDeclaration
 * @phpcs:disable Squiz.Classes.ValidClassName.NotCamelCaps
 * @phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
 * @SuppressWarnings(PHPMD.CamelCaseClassName)
 */
class Shopware_Controllers_Frontend_BillieInvoice extends Enlight_Controller_Action implements CSRFWhitelistAware
{
    /**
     * Display Invoice PDF if authenticated and found.
     *
     * @return void
     */
    public function invoiceAction()
    {
        // Get Request Params.
        $invoiceNumber = $this->Request()->getParam('invoiceNumber');
        $apikey = $this->Request()->getParam('apikey');
        $hash = $this->Request()->getParam('hash');

        // Return 401 Error if not authenticated or not a post request.
        if (!$this->Request()->isPost() || !$this->authenticateRequest($invoiceNumber, $hash, $apikey)) {
            $this->Response()->setHttpResponseCode(401);

            return;
        }

        // Return 404 Error if document was not found.
        if (!($document = $this->getDocument($hash))) {
            $this->Response()->setHttpResponseCode(404);

            return;
        }

        // Send Document to Browser
        $this->sendDocument($document);
    }

    /**
     * Disable Rendering.
     *
     * @return void
     */
    public function preDispatch()
    {
        $this->Front()->Plugins()->ViewRenderer()->setNoRender();
    }

    /**
     * Whitelisted CSRF actions.
     *
     * @return array
     */
    public function getWhitelistedCSRFActions()
    {
        return ['invoice'];
    }

    /**
     * Authenticate the request.
     *
     * @param string $invoiceNumber
     * @param string $hash
     * @param string $apikey
     *
     * @return bool
     */
    protected function authenticateRequest($invoiceNumber, $hash, $apikey)
    {
        /** @var \BilliePayment\Components\Utils $utils */
        $utils = $this->container->get('billie_payment.utils');
        $config = $utils->getPluginConfig();

        // Check for valid api key
        if ($config['apikey'] !== $apikey) {
            $this->Response()->setHttpResponseCode(401);

            return;
        }

        // Check for correct invoice number and hash
        $models = $utils->getEnityManager();
        $repo = $models->getRepository(Document::class);

        return $repo->findOneBy(['documentId' => $invoiceNumber, 'hash' => $hash]) !== null;
    }

    /**
     * Send PDF document to browser.
     *
     * @param string $file
     *
     * @return void
     */
    protected function sendDocument($file)
    {
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

    /**
     * Get the document path if the document exists,
     *
     * @param string $hash
     *
     * @return string|bool
     */
    protected function getDocument($hash)
    {
        $file = Shopware()->DocPath() . "files/documents/{$hash}.pdf";

        if (!file_exists($file)) {
            return false;
        }

        return $file;
    }
}
