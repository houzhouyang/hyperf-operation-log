<?php

declare(strict_types=1);

namespace HyperfOperationLog\ExpressionHandlers;

use HyperfOperationLog\Contracts\OperationLogExpressionHandlerInterface;
use HyperfOperationLog\Services\OperationLogParseService;

class ObjectMethodHandler implements OperationLogExpressionHandlerInterface
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
        
        if (preg_match('/^\(obj\)(\w+)\.(\w+)\((.*?)\)$/', $expression, $methodMatches)) {
            $key = $methodMatches[1];
            $method = $methodMatches[2];
            $argsString = $methodMatches[3];

            $object = $service->getObjectFromContextOrContainer($key, $context);
            if ($object) {
                $args = $service->parseArguments($argsString);
                return $service->callObjectMethod($object, $method, $args);
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
        return 'object_method';
    }
    
    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return '处理对象方法调用，格式为：{(obj)object.method(arg1,arg2)}';
    }
} 