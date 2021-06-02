<?php

namespace BilliePayment\Compiler;

use Monolog\Logger;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class FileLoggerPass implements CompilerPassInterface
{
    /**
     * @var string
     */
    private $containerPrefix;

    /**
     * @param string $containerPrefix
     */
    public function __construct($containerPrefix)
    {
        $this->containerPrefix = $containerPrefix;
    }

    public function process(ContainerBuilder $container)
    {
        $loggerServiceName = $this->containerPrefix . '.logger';
        $loggerHandlerServiceName = $this->containerPrefix . '.logger_handler';
        $loggerFormatterServiceName = $this->containerPrefix . '.logger_formatter';

        $loggerParamMaxFilesName = $this->containerPrefix . '.max_files';
        $loggerParamLevelName = $this->containerPrefix . '.level';

        if ($container->hasParameter($loggerParamMaxFilesName) === false) {
            $container->setParameter($loggerParamMaxFilesName, 5);
            $container->setParameter($loggerParamLevelName, Logger::DEBUG);
        }

        if ($container->has($loggerServiceName) === false) {
            $container->register($loggerHandlerServiceName, \Monolog\Handler\RotatingFileHandler::class)
                ->addArgument('%kernel.logs_dir%/' . $this->containerPrefix . '_%kernel.environment%.log')
                ->addArgument('%' . $loggerParamMaxFilesName . '%')
                ->addArgument('%' . $loggerParamLevelName . '%')
                ->setPublic(true);

            $container->register($loggerFormatterServiceName, \Monolog\Processor\PsrLogMessageProcessor::class)
                ->setPublic(true);

            $container->register($loggerServiceName, Logger::class)
                ->addArgument($this->containerPrefix)
                ->addArgument([new Reference($loggerHandlerServiceName)])
                ->addArgument([new Reference($loggerFormatterServiceName)])
                ->setPublic(true);
        }
    }
}
