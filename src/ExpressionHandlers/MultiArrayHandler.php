<?php

declare(strict_types=1);

namespace HyperfOperationLog\ExpressionHandlers;

use HyperfOperationLog\Contracts\OperationLogExpressionHandlerInterface;

class MultiArrayHandler implements OperationLogExpressionHandlerInterface
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
        if (preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)\.arr\.([a-zA-Z0-9_]+)$/', $expression, $paramMatches)) {
            $key = $paramMatches[1];
            $index = $paramMatches[2];
            
            if (isset($context[$key]) && is_array($context[$key])) {
                $result = array_map(
                    fn($subArray) => is_array($subArray) && isset($subArray[$index]) ? $subArray[$index] : null,
                    $context[$key]
                );
                $result = array_filter($result, fn($item) => $item !== null);
                return json_encode($result, JSON_UNESCAPED_UNICODE);
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
        return 'multi_array';
    }
    
    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return '处理多维数组访问，格式为：{param.arr.key}';
    }
} 