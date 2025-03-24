<?php

declare(strict_types=1);

namespace HyperfOperationLog\ExpressionHandlers;

use HyperfOperationLog\Contracts\OperationLogExpressionHandlerInterface;

class ArrayItemHandler implements OperationLogExpressionHandlerInterface
{
    /**
     * 处理表达式
     * 
     * @param string $expression 表达式
     * @param array $context 上下文数据
     * @param object $service 服务对象
     * @return string|null 处理结果，如果无法处理则返回null
     */
    public function handle(string $expression, array $context, object $service): ?string
    {
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z0-9_]+)$/', $expression, $paramMatches)) {
            $key = $paramMatches[1];
            $index = $paramMatches[2];
            
            if (isset($context[$key]) && is_array($context[$key]) && isset($context[$key][$index])) {
                $value = $context[$key][$index];
                return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string)$value;
            }
        }
        
        return null;
    }
    
    /**
     * 获取处理器名称
     *
     * @return string
     */
    public function getName(): string
    {
        return 'array_item';
    }
    
    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return '处理数组元素访问，格式为：{param.key}';
    }
} 