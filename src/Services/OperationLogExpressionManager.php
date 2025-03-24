<?php

declare(strict_types=1);

namespace HyperfOperationLog\Services;

use HyperfOperationLog\Contracts\OperationLogExpressionHandlerInterface;

class OperationLogExpressionManager
{
    /**
     * 表达式处理器集合
     *
     * @var array<string, OperationLogExpressionHandlerInterface>
     */
    protected array $handlers = [];

    /**
     * 注册表达式处理器
     *
     * @param OperationLogExpressionHandlerInterface $handler
     * @return self
     */
    public function register(OperationLogExpressionHandlerInterface $handler): self
    {
        $this->handlers[$handler->getName()] = $handler;
        return $this;
    }

    /**
     * 移除表达式处理器
     *
     * @param string $name
     * @return self
     */
    public function remove(string $name): self
    {
        if (isset($this->handlers[$name])) {
            unset($this->handlers[$name]);
        }
        return $this;
    }

    /**
     * 获取所有处理器
     *
     * @return array<string, OperationLogExpressionHandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    /**
     * 获取指定处理器
     *
     * @param string $name
     * @return OperationLogExpressionHandlerInterface|null
     */
    public function getHandler(string $name): ?OperationLogExpressionHandlerInterface
    {
        return $this->handlers[$name] ?? null;
    }

    /**
     * 处理表达式
     *
     * @param string $expression 表达式
     * @param array $context 上下文数据
     * @param object $service 服务对象
     * @return string|null 处理结果，如果无法处理则返回null
     */
    public function process(string $expression, array $context, object $service): ?string
    {
        foreach ($this->handlers as $handler) {
            $result = $handler->handle($expression, $context, $service);
            if ($result !== null) {
                return $result;
            }
        }
        
        return null;
    }
} 