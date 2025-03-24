<?php

declare(strict_types=1);

namespace HyperfOperationLog\Jobs;

use HyperfOperationLog\Contracts\OperationLogStorageInterface;
use HyperfOperationLog\Events\OperationLogCreatedEvent;
use Hyperf\AsyncQueue\Job;
use Hyperf\Context\ApplicationContext;

class SaveOperationLogJob extends Job
{
    /**
     * @var OperationLogCreatedEvent
     */
    protected OperationLogCreatedEvent $event;

    /**
     * 构造函数
     *
     * @param OperationLogCreatedEvent $event
     */
    public function __construct(OperationLogCreatedEvent $event)
    {
        $this->event = $event;
    }

    /**
     * 执行任务
     */
    public function handle()
    {
        try {
            // 从容器中获取存储服务
            $container = ApplicationContext::getContainer();
            $storage = $container->get(OperationLogStorageInterface::class);
            
            // 存储操作日志
            $storage->store($this->event);
        } catch (\Throwable $e) {
            // 记录异常，但不重试，防止无限循环
            // 可以在这里添加日志记录
        }
    }
} 