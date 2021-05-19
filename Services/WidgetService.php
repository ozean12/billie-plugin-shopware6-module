<?php

namespace BilliePayment\Services;

use Billie\Sdk\Model\LineItem;
use Billie\Sdk\Model\Person;
use BilliePayment\Helper\BasketHelper;
use Monolog\Logger;
use Shopware\Bundle\StoreFrontBundle\Service\ContextServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Service\ProductServiceInterface;
use Shopware\Bundle\StoreFrontBundle\Struct\Product;

class WidgetService
{
    /**
     * @var ProductServiceInterface
     */
    private $productService;

    /**
     * @var ConfigService
     */
    private $configService;

    /**
     * @var SessionService
     */
    private $sessionService;

    /**
     * @var ContextServiceInterface
     */
    private $contextService;

    /**
     * @var Logger
     */
    private $logger;

    public function __construct(
        ProductServiceInterface $productService,
        ConfigService $configService,
        SessionService $sessionService,
        ContextServiceInterface $contextService
    ) {
        $this->productService = $productService;
        $this->configService = $configService;
        $this->sessionService = $sessionService;
        $this->contextService = $contextService;
    }

    /** @noinspection NullPointerExceptionInspection */
    public function getWidgetData(array $sOrderVariables)
    {
        $customer = $this->sessionService->getCustomer();
        $shopwareBillingAddress = $this->sessionService->getShopwareBillingAddress();

        $checkoutSessionId = $this->sessionService->getCheckoutSessionId(true);

        return [
            'src' => $this->configService->isSandbox() ? 'https://static-paella-sandbox.billie.io/checkout/billie-checkout.js' : 'https://static.billie.io/checkout/billie-checkout.js',
            'checkoutSessionId' => $checkoutSessionId,
            'checkoutData' => [
                'amount' => $this->sessionService->getTotalAmount()->toArray(),
                'duration' => $this->sessionService->getBillieDurationForPaymentMethod(),
                'debtor_company' => $this->sessionService->getDebtorCompany()->toArray(),
                'delivery_address' => $this->sessionService->getShippingAddress()->toArray(),
                'debtor_person' => (new Person())
                    ->setValidateOnSet(false)
                    ->setSalutation($this->transformSalutation($shopwareBillingAddress->getSalutation()))
                    ->setFirstname($shopwareBillingAddress->getFirstname())
                    ->setLastname($shopwareBillingAddress->getLastname())
                    ->setPhone($shopwareBillingAddress->getPhone())
                    ->setMail($customer->getEmail())
                    ->toArray(),
                'line_items' => $this->getLineItems($sOrderVariables['sBasket']['content']),
            ],
        ];
    }

    protected function transformSalutation($salutation)
    {
        $salutations = $this->configService->getSalutationMapping();
        if (in_array($salutation, $salutations['male'], true)) {
            return 'm';
        }

        if (in_array($salutation, $salutations['female'], true)) {
            return 'f';
        }

        return $this->configService->getFallbackSalutation();
    }

    protected function getLineItems(array $content)
    {
        $lineItems = [];
        foreach ($content as $item) {
            if ($item['modus'] != 0) {
                // item is not a product (it is a voucher etc.). Billie does only accepts real products
                continue;
            }
            $product = $this->productService->get($item['ordernumber'], $this->contextService->getProductContext());
            $lineItems[] = $this->getLineItem($item, $product);
        }

        return $lineItems;
    }

    protected function getLineItem(array $item, Product $product = null)
    {
        $categories = $product ? $product->getCategories() : null;

        return (new LineItem())
            ->setExternalId($item['ordernumber'])
            ->setTitle($item['articlename'])
            ->setDescription($product ? substr($product->getShortDescription(), 0, 255) : null)
            ->setQuantity((int) $item['quantity'])
            ->setCategory($categories && isset($categories[0]) ? implode(' > ', $categories[0]->getPath()) : null)
            ->setBrand($product && $product->getManufacturer() ? $product->getManufacturer()->getName() : null)
            ->setGtin($product ? $product->getEan() : null)
            ->setMpn(null)
            ->setAmount(BasketHelper::getProductAmount($item))
            ->toArray();
    }
}
