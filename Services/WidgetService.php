<?php

namespace BilliePayment\Services;

use BilliePayment\Helper\BasketHelper;
use kamermans\OAuth2\Exception\AccessTokenRequestException;
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
        ContextServiceInterface $contextService,
        Logger $logger
    ) {
        $this->productService = $productService;
        $this->configService = $configService;
        $this->sessionService = $sessionService;
        $this->contextService = $contextService;
        $this->logger = $logger;
    }

    public function getWidgetData(array $sOrderVariables)
    {
        $customer = $this->sessionService->getCustomer();
        $billingAddress = $this->sessionService->getBillingAddress();
        $shippingAddress = $this->sessionService->getShippingAddress();

        try {
            $checkoutSessionId = $this->sessionService->getCheckoutSessionId(true);
        } catch (AccessTokenRequestException $e) {
            $this->logger->addCritical($e->getMessage());
            $checkoutSessionId = '';
        }

        $widgetData = [
            'src' => $this->configService->isSandbox() ? 'https://static-paella-sandbox.billie.io/checkout/billie-checkout.js' : 'https://static.billie.io/checkout/billie-checkout.js',
            'checkoutSessionId' => $checkoutSessionId,
            'checkoutData' => [
                'amount' => $this->sessionService->getTotalAmount(),
                'duration' => $this->sessionService->getBillieDurationForPaymentMethod(),
                'delivery_address' => [
                    'street' => $this->extractStreet($shippingAddress->getStreet()),
                    'house_number' => $this->extractStreetNumber($shippingAddress->getStreet()),
                    'addition' => $shippingAddress->getAdditional() ? implode(', ', $shippingAddress->getAdditional()) : null,
                    'city' => $shippingAddress->getCity(),
                    'postal_code' => $shippingAddress->getZipcode(),
                    'country' => $shippingAddress->getCountry()->getIso(),
                ],
                'debtor_company' => [
                    'name' => $billingAddress->getCompany() ? $billingAddress->getCompany() : $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
                    'established_customer' => false,
                    'address_street' => $this->extractStreet($billingAddress->getStreet()),
                    'address_house_number' => $this->extractStreetNumber($billingAddress->getStreet()),
                    'address_addition' => $billingAddress->getAdditional() ? implode(', ', $billingAddress->getAdditional()) : null,
                    'address_city' => $billingAddress->getCity(),
                    'address_postal_code' => $billingAddress->getZipcode(),
                    'address_country' => $billingAddress->getCountry()->getIso(),
                ],
                'debtor_person' => [
                    'salutation' => $this->transformSalutation($billingAddress->getSalutation()),
                    'first_name' => $billingAddress->getFirstname(),
                    'last_name' => $billingAddress->getLastname(),
                    'phone_number' => $billingAddress->getPhone(),
                    'email' => $customer->getEmail(),
                ],
                'line_items' => $this->getLineItems($sOrderVariables['sBasket']['content']),
            ],
        ];

        return $widgetData;
    }

    protected function extractStreet($street)
    {
        preg_match('/(.*) [0-9]+ {0,1}[A-Za-z]*/', $street, $matches);

        return isset($matches[1]) ? $matches[1] : $street;
    }

    protected function extractStreetNumber($street)
    {
        preg_match('/.* ([0-9]+ {0,1}[A-Za-z]*)/', $street, $matches);

        return $matches[1];
    }

    protected function transformSalutation($salutation)
    {
        $salutations = $this->configService->getSalutationMapping();
        if (in_array($salutation, $salutations['male'])) {
            return 'm';
        } elseif (in_array($salutation, $salutations['female'])) {
            return 'f';
        } else {
            return $this->configService->getFallbackSalutation();
        }
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
        /* Shipping costs are currently not needed
         * if ($basket['sShippingcosts'] > 0) {
            $lineItems[] = [
                'external_id' => 'shipping',
                'title' => 'Versandkosten',
                'description' => null,
                'quantity' => 1,
                'category' => null,
                'brand' => null,
                'gtin' => null,
                'mpn' => null,
                'amount' => BasketHelper::getShippingAmount($basket)
            ];
        }*/
        return $lineItems;
    }

    protected function getLineItem(array $item, Product $product = null)
    {
        $categories = $product ? $product->getCategories() : null;

        return [
            'external_id' => $item['ordernumber'],
            'title' => $item['articlename'],
            'description' => $product ? substr($product->getShortDescription(), 0, 255) : null,
            'quantity' => $item['quantity'],
            'category' => $categories && isset($categories[0]) ? implode(' > ', $categories[0]->getPath()) : null,
            'brand' => $product && $product->getManufacturer() ? $product->getManufacturer()->getName() : null,
            'gtin' => $product ? $product->getEan() : null,
            'mpn' => null, // is not supported by shopware
            'amount' => BasketHelper::getProductAmount($item),
        ];
    }
}
