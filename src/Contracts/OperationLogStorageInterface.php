<?php

declare(strict_types=1);

namespace HyperfOperationLog\Contracts;

use HyperfOperationLog\Events\OperationLogCreatedEvent;

interface OperationLogStorageInterface
{
    /**
     * 存储操作日志
     * 
     * @param OperationLogCreatedEvent $event 操作日志事件
     */
    public function store(OperationLogCreatedEvent $event): void;
} 