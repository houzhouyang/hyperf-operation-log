<?php

declare(strict_types=1);

namespace HyperfOperationLog\Services;

use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;
use Hyperf\Context\ApplicationContext;

/**
 * 配置适配器，兼容 Hyperf 2.2 和 3.0 的配置获取方式
 */
class ConfigAdapter
{
    /**
     * @var ConfigInterface
     */
    protected ConfigInterface $config;

    /**
     * @var bool 是否使用函数风格获取配置（Hyperf 2.2）
     */
    protected bool $useFunctionStyle = false;

    /**
     * 构造函数
     *
     * @param ConfigInterface $config
     */
    public function __construct(ConfigInterface $config)
    {
        $this->config = $config;
        
        // 检测是否需要使用函数风格
        $this->detectConfigStyle();
    }

    /**
     * 获取配置
     *
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public function get(string $key, $default = null)
    {
        if ($this->useFunctionStyle) {
            // Hyperf 2.2 风格：使用全局函数
            return config($key, $default);
        } else {
            // Hyperf 3.0 风格：使用 ConfigInterface
            return $this->config->get($key, $default);
        }
    }

    /**
     * 设置配置
     *
     * @param string $key 配置键名
     * @param mixed $value 配置值
     * @return void
     */
    public function set(string $key, $value): void
    {
        if ($this->useFunctionStyle) {
            // Hyperf 2.2 风格：没有直接的函数，通过调用 ConfigInterface
            $this->config->set($key, $value);
        } else {
            // Hyperf 3.0 风格：使用 ConfigInterface
            $this->config->set($key, $value);
        }
    }

    /**
     * 检测应该使用的配置风格
     */
    protected function detectConfigStyle(): void
    {
        if (function_exists('config')) {
            try {
                // 尝试调用 config 函数，如果没有抛出异常或给出警告，则认为是 Hyperf 2.2
                $result = @config('app.name');
                if ($result !== null || $result === null) {
                    $this->useFunctionStyle = true;
                    return;
                }
            } catch (\Throwable $e) {
                // 调用失败，可能是函数存在但已废弃或无法使用
            }
        }
        
        // 默认使用 ConfigInterface 方式 (Hyperf 3.0)
        $this->useFunctionStyle = false;
    }
    
    /**
     * 从容器中创建适配器的静态方法
     *
     * @return self
     */
    public static function create(): self
    {
        $container = ApplicationContext::getContainer();
        $config = $container->get(ConfigInterface::class);
        
        return new self($config);
    }
    
    /**
     * 静态方法，根据环境自动选择合适的配置获取方式
     *
     * @param string $key 配置键名
     * @param mixed $default 默认值
     * @return mixed 配置值
     */
    public static function getConfig(string $key, $default = null)
    {
        // 检查是否存在 config 函数并可用，适用于 Hyperf 2.2
        if (function_exists('config')) {
            try {
                $config = @config($key, $default);
                // 如果没有抛出异常，说明函数可用
                return $config;
            } catch (\Throwable $e) {
                // 函数存在但不可用，继续尝试其他方式
            }
        }
        
        // 使用 ConfigInterface，适用于 Hyperf 3.0
        try {
            $container = ApplicationContext::getContainer();
            if ($container->has(ConfigInterface::class)) {
                $config = $container->get(ConfigInterface::class);
                return $config->get($key, $default);
            }
        } catch (\Throwable $e) {
            // 获取容器或配置失败
        }
        
        // 兜底返回默认值
        return $default;
    }
} 