<?php

declare(strict_types=1);

namespace HyperfOperationLog\Services;

use HyperfOperationLog\Events\OperationLogCreatedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

class OperationLogStorageService
{
    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    /**
     * 保存操作日志
     *
     * @param string $bizNo 业务编号
     * @param string $content 操作内容
     * @param string $category 操作类别
     * @param string $action 操作动作
     * @param string $userName 操作人姓名
     * @param string $userId 操作人ID
     * @param array $requestData 请求数据
     * @param array $responseData 响应数据
     * @param array $extraParams 额外参数
     * @param ?string $organizationCode 组织编码
     */
    public function saveOperationLog(
        string $bizNo,
        string $content,
        string $category,
        string $action,
        string $userName,
        string $userId,
        array $requestData,
        array $responseData,
        array $extraParams = [],
        ?string $organizationCode = null
    ): void {
        $operationLogCreatedEvent = new OperationLogCreatedEvent(
            $bizNo,
            $content,
            $category,
            $action,
            $userName,
            $userId,
            $requestData,
            $responseData,
            $extraParams,
            $organizationCode
        );
        
        $this->dispatcher->dispatch($operationLogCreatedEvent);
    }
} 