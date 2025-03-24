<?php

declare(strict_types=1);

namespace HyperfOperationLog\Listeners;

use HyperfOperationLog\Contracts\OperationLogStorageInterface;
use HyperfOperationLog\Events\OperationLogCreatedEvent;
use HyperfOperationLog\Jobs\SaveOperationLogJob;
use HyperfOperationLog\QueueFactory;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Event\Annotation\Listener;
use Hyperf\Config\Annotation\Value;

#[Listener]
class AsyncOperationLogListener extends OperationLogSaveListener
{
    /**
     * @var QueueFactory
     */
    protected QueueFactory $queueFactory;
    
    
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    protected ?string $customListener = null;

    /**
     * 构造函数，注入依赖
     */
    public function __construct(
        ContainerInterface $container,
        OperationLogStorageInterface $storage,
        ConfigInterface $config
    ) {
        parent::__construct($container, $storage, $config);
        // 创建队列工厂实例
        $this->queueFactory = new QueueFactory($container, $config);
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
        
        // 记录日志
        $this->logger->info('异步处理操作日志', ['event_id' => $event->bizNo]);
        
        try {
            // 获取队列驱动
            $driver = $this->queueFactory->getDriver();
            
            // 将事件放入队列
            $driver->push(new SaveOperationLogJob($event));
        } catch (\Throwable $e) {
            // 记录错误
            $this->logger->error('队列推送失败，降级为同步处理', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // 队列推送失败，降级为同步处理
            parent::process($event);
        }
    }
} 