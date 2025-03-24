<?php

declare(strict_types=1);

namespace HyperfOperationLog\ExpressionHandlers;

use HyperfOperationLog\Contracts\OperationLogExpressionHandlerInterface;
use HyperfOperationLog\Services\OperationLogParseService;

class ObjectPropertyHandler implements OperationLogExpressionHandlerInterface
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
        if (!$service instanceof OperationLogParseService) {
            return null;
        }
        
        if (preg_match('/^\(obj\)(\w+)\.(\w+)$/', $expression, $propertyMatches)) {
            $key = $propertyMatches[1];
            $property = $propertyMatches[2];
            
            $object = $service->getObjectFromContextOrContainer($key, $context);
            if ($object) {
                return $service->getObjectProperty($object, $property);
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
        return 'object_property';
    }
    
    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return '处理对象属性访问，格式为：{(obj)object.property}';
    }
} 