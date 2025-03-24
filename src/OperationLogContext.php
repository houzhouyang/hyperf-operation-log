<?php

declare(strict_types=1);

namespace HyperfOperationLog;

use Hyperf\Context\Context;

class OperationLogContext
{
    /**
     * 上下文数据键名
     */
    protected static string $contextKey = 'operation-log.context';

    /**
     * 获取日志上下文数据
     * 
     * @return array|null 上下文数据
     */
    public static function getLogContextData(): ?array
    {
        return Context::get(static::$contextKey);
    }

    /**
     * 根据键获取日志上下文数据
     * 
     * @param string $key 数据键名
     * @param mixed $default 默认值
     * @return mixed 上下文数据值
     */
    public static function getLogContextDataByKey(string $key, $default = '')
    {
        $data = static::getLogContextData() ?? [];
        return $data[$key] ?? $default;
    }

    /**
     * 设置日志上下文数据
     * 
     * @param array $data 上下文数据
     */
    public static function setLogContextData(array $data): void
    {
        Context::set(static::$contextKey, $data);
    }

    /**
     * 添加日志上下文数据
     * 
     * @param string $key 键名
     * @param mixed $value 数据值
     */
    public static function addLogContextData(string $key, $value): void
    {
        $contextData = static::getLogContextData() ?? [];
        $contextData[$key] = $value;
        static::setLogContextData($contextData);
    }

    /**
     * 从日志上下文中移除数据
     * 
     * @param string $key 键名
     */
    public static function removeLogContextData(string $key): void
    {
        $contextData = static::getLogContextData() ?? [];
        unset($contextData[$key]);
        static::setLogContextData($contextData);
    }

    /**
     * 清空日志上下文数据
     */
    public static function clearLogContextData(): void
    {
        Context::set(static::$contextKey, null);
    }
} 