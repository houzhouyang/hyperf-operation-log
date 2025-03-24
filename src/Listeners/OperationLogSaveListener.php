<?php

declare(strict_types=1);

namespace HyperfOperationLog\Listeners;

use HyperfOperationLog\Contracts\OperationLogStorageInterface;
use HyperfOperationLog\Events\OperationLogCreatedEvent;
use Hyperf\Event\Contract\ListenerInterface;
use Psr\Container\ContainerInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Contract\ConfigInterface;

#[Listener]
class OperationLogSaveListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;
    
    /**
     * @var OperationLogStorageInterface
     */
    protected OperationLogStorageInterface $storage;
    
    /**
     * @var ConfigInterface
     */
    protected ConfigInterface $config;
    
    /**
     * 使用注解注入LoggerFactory
     */
    #[Inject]
    protected LoggerFactory $loggerFactory;
    
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(
        ContainerInterface $container,
        OperationLogStorageInterface $storage,
        ConfigInterface $config
    ) {
        $this->container = $container;
        $this->storage = $storage;
        $this->config = $config;
        
        // 使用注入的LoggerFactory获取logger
        $this->logger = $this->loggerFactory->get('operation_log');
    }

    public function listen(): array
    {
        return [
            OperationLogCreatedEvent::class,
        ];
    }

    /**
     * @param OperationLogCreatedEvent $event
     */
    public function process(object $event): void
    {
        // 如果配置了自定义监听器，则跳过处理
        if ($this->config->get('operation_log.custom_listener')) {
            return;
        }

        if (!$event instanceof OperationLogCreatedEvent) {
            return;
        }

        try {
            $this->storage->store($event);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to save operation log: ' . $e->getMessage());
        }
    }
} 