<?php

declare(strict_types=1);

namespace HyperfOperationLog\ExpressionHandlers;

use HyperfOperationLog\Contracts\OperationLogExpressionHandlerInterface;

class DateFormatHandler implements OperationLogExpressionHandlerInterface
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
        // 处理日期格式化表达式 (date)format:Y-m-d H:i:s
        if (preg_match('/^\(date\)(.+?)(?::(.+))?$/', $expression, $matches)) {
            $format = $matches[2] ?? 'Y-m-d H:i:s';
            
            if (count($matches) > 1) {
                if (strtolower($matches[1]) === 'now') {
                    // 当前时间
                    return date($format);
                } elseif (isset($context[$matches[1]]) && !empty($context[$matches[1]])) {
                    // 从上下文获取时间戳或日期字符串
                    $time = $context[$matches[1]];
                    if (is_numeric($time)) {
                        return date($format, (int)$time);
                    } else {
                        $timestamp = strtotime((string)$time);
                        if ($timestamp !== false) {
                            return date($format, $timestamp);
                        }
                    }
                }
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
        return 'date_format';
    }
    
    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return '处理日期格式化，格式为：{(date)now:Y-m-d} 或 {(date)timestamp:H:i}';
    }
} 