<?php

declare(strict_types=1);

namespace HyperfOperationLog;

use HyperfOperationLog\Services\ConfigAdapter;
use Hyperf\AsyncQueue\Driver\DriverFactory;
use Hyperf\AsyncQueue\Driver\DriverInterface;
use Hyperf\AsyncQueue\Exception\InvalidDriverException;
use Psr\Container\ContainerInterface;
use Hyperf\Contract\ConfigInterface;

class QueueFactory
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;
    
    /**
     * @var ConfigInterface
     */
    protected ConfigInterface $config;
    
    /**
     * 默认队列名称
     */
    protected string $defaultQueueName = 'default';

    /**
     * 构造函数
     *
     * @param ContainerInterface $container
     * @param ConfigInterface $config
     */
    public function __construct(ContainerInterface $container, ConfigInterface $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    /**
     * 获取队列驱动
     *
     * @return DriverInterface
     * @throws InvalidDriverException
     */
    public function getDriver(): DriverInterface
    {
        $driverFactory = $this->container->get(DriverFactory::class);
        
        // 使用配置适配器兼容 Hyperf 2.2 和 3.0
        $queueName = ConfigAdapter::getConfig('operation_log.async.queue', $this->defaultQueueName);
        
        // 验证队列是否已配置
        $config = $this->container->get(ConfigInterface::class);
        if (!$config->has('async_queue.' . $queueName)) {
            // 如果队列没有配置，使用默认队列
            $queueName = $this->defaultQueueName;
            // 如果默认队列也没有配置，抛出异常
            if (!$config->has('async_queue.' . $queueName)) {
                throw new InvalidDriverException(sprintf(
                    "Queue '%s' is not configured. Please check your async_queue configuration.",
                    $queueName
                ));
            }
        }
        
        return $driverFactory->get($queueName);
    }
} 