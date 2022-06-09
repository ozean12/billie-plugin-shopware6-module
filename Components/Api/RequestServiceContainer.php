<?php

namespace BilliePayment\Components\Api;

use Billie\Sdk\Service\Request\AbstractRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

class RequestServiceContainer
{
    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @template T of \Billie\Sdk\Service\Request\AbstractRequest
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function get($class)
    {
        $object = $this->container->get($class);
        if (!$object instanceof AbstractRequest) {
            throw new \InvalidArgumentException('Class ' . $class . ' does not implement ' . AbstractRequest::class);
        }

        return $object; // @phpstan-ignore-line
    }
}
