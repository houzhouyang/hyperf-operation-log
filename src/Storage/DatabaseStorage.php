<?php

declare(strict_types=1);

namespace HyperfOperationLog\Storage;

use HyperfOperationLog\Contracts\OperationLogStorageInterface;
use HyperfOperationLog\Events\OperationLogCreatedEvent;
use HyperfOperationLog\Models\OperationLog;
use Psr\Log\LoggerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;

class DatabaseStorage implements OperationLogStorageInterface
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger = null;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        
        if ($container->has(LoggerFactory::class)) {
            $this->logger = $container->get(LoggerFactory::class)->get('operation-log');
        }
    }

    /**
     * 存储操作日志到数据库
     * 
     * @param OperationLogCreatedEvent $event 操作日志事件
     */
    public function store(OperationLogCreatedEvent $event): void
    {
        try {
            OperationLog::create([
                'biz_no' => $event->bizNo,
                'content' => $event->content,
                'category' => $event->category,
                'action' => $event->action,
                'user_name' => $event->userName,
                'user_id' => $event->userId,
                'organization_code' => $event->organizationCode,
                'request_data' => $event->requestData,
                'response_data' => $event->responseData,
                'extra_params' => $event->extraParams,
            ]);
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->error('保存操作日志失败', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => [
                        'biz_no' => $event->bizNo,
                        'content' => $event->content,
                        'category' => $event->category,
                        'action' => $event->action,
                    ],
                ]);
            }
        }
    }
} 