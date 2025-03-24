<?php

declare(strict_types=1);

namespace HyperfOperationLog\Contracts;

interface OperationLogExpressionHandlerInterface
{
    /**
     * 处理表达式
     * 
     * @param string $expression 表达式
     * @param array $context 上下文数据
     * @param object $service 服务对象，用于提供额外方法
     * @return string|null 处理结果，如果无法处理则返回null
     */
    public function handle(string $expression, array $context, object $service): ?string;
    
    /**
     * 获取处理器名称
     *
     * @return string
     */
    public function getName(): string;
    
    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function getDescription(): string;
} 