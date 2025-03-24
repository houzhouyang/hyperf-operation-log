<?php

declare(strict_types=1);

namespace HyperfOperationLog\Services;

use HyperfOperationLog\Contracts\OperationLogParseServiceInterface;
use HyperfOperationLog\Contracts\OperationLogExpressionHandlerInterface;
use HyperfOperationLog\ExpressionHandlers\ObjectMethodHandler;
use HyperfOperationLog\ExpressionHandlers\ObjectPropertyHandler;
use HyperfOperationLog\ExpressionHandlers\ArrayItemHandler;
use HyperfOperationLog\ExpressionHandlers\MultiArrayHandler;
use HyperfOperationLog\ExpressionHandlers\ContextVariableHandler;
use Psr\Container\ContainerInterface;
use Hyperf\Di\Container;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;

class OperationLogParseService implements OperationLogParseServiceInterface
{
    /**
     * 要解析的上下文
     */
    protected array $context;

    /**
     * 操作日志服务映射配置
     */
    protected array $operationLogMap;

    /**
     * 模板队列
     */
    protected array $templates = [];
    
    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;
    
    /**
     * @var ConfigAdapter
     */
    private ConfigAdapter $configAdapter;
    
    /**
     * 表达式处理器管理器
     * 
     * @var OperationLogExpressionManager
     */
    protected OperationLogExpressionManager $expressionManager;

    /**
     * 构造函数
     *
     * @param array $context 上下文数据
     * @param ConfigInterface $config 配置接口
     */
    public function __construct(array $context, ConfigInterface $config)
    {
        $this->context = $context;
        $this->config = $config;
        $this->configAdapter = new ConfigAdapter($config);
        $this->operationLogMap = $this->configAdapter->get('operation_log.service_map', []);
        $this->expressionManager = new OperationLogExpressionManager();
        
        // 注册处理器
        $this->registerHandlersFromConfig();
    }

    /**
     * 从配置中注册处理器
     */
    protected function registerHandlersFromConfig(): void
    {
        $handlersConfig = $this->configAdapter->get('operation_log.expression_handlers', []);
        $disableDefault = $handlersConfig['disable_default'] ?? false;
        
        // 如果没有禁用默认处理器，则注册它们
        if (!$disableDefault) {
            // 获取默认处理器配置
            $defaultHandlers = $handlersConfig['default'] ?? [];
            
            // 如果配置为空或未配置，注册所有默认处理器
            if (empty($defaultHandlers)) {
                $this->registerDefaultHandlers();
            } else {
                // 否则按配置有选择地注册
                $this->registerSelectiveDefaultHandlers($defaultHandlers);
            }
        }
        
        // 注册自定义处理器
        $customHandlers = $handlersConfig['custom'] ?? [];
        foreach ($customHandlers as $handlerClass) {
            if (class_exists($handlerClass)) {
                try {
                    $handler = new $handlerClass();
                    if ($handler instanceof OperationLogExpressionHandlerInterface) {
                        $this->expressionManager->register($handler);
                    }
                } catch (\Throwable $e) {
                    // 忽略处理器初始化错误
                }
            }
        }
    }
    
    /**
     * 有选择地注册默认处理器
     * 
     * @param array $handlers 处理器配置
     */
    protected function registerSelectiveDefaultHandlers(array $handlers): void
    {
        // 检查每个默认处理器是否启用
        if (($handlers[ObjectMethodHandler::class] ?? true) === true) {
            $this->expressionManager->register(new ObjectMethodHandler());
        }
        
        if (($handlers[ObjectPropertyHandler::class] ?? true) === true) {
            $this->expressionManager->register(new ObjectPropertyHandler());
        }
        
        if (($handlers[MultiArrayHandler::class] ?? true) === true) {
            $this->expressionManager->register(new MultiArrayHandler());
        }
        
        if (($handlers[ArrayItemHandler::class] ?? true) === true) {
            $this->expressionManager->register(new ArrayItemHandler());
        }
        
        if (($handlers[ContextVariableHandler::class] ?? true) === true) {
            $this->expressionManager->register(new ContextVariableHandler());
        }
    }

    /**
     * 添加模板到解析队列
     * 
     * @param string $template 模板字符串
     */
    public function add(string $template): void
    {
        $this->templates[] = $template;
    }

    /**
     * 解析队列中的所有模板内容
     * 
     * @return array 解析后的内容数组
     */
    public function parse(): array
    {
        try {
            return array_map(function ($template) {
                return $this->parseTemplate($template);
            }, $this->templates);
        } catch (\Throwable $e) {
            try {
                // 尝试获取日志记录器
                $container = ApplicationContext::getContainer();
                if ($container->has(LoggerFactory::class)) {
                    $logger = $container->get(LoggerFactory::class)->get('operation-log');
                    $logger->error('解析操作日志模板失败', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            } catch (\Throwable $logException) {
                // 日志记录失败，忽略
            }
            return $this->templates;
        }
    }

    /**
     * 解析模板字符串中的占位符 {}，并根据 (obj) 标识获取对象属性
     * 
     * @param string $template 模板字符串
     * @return string 解析后的字符串
     */
    public function parseTemplate(string $template): string
    {
        $context = $this->context;
        
        return preg_replace_callback('/\{(.*?)\}/', function ($matches) use ($context) {
            $expression = $matches[1];
            
            // 使用表达式处理器处理
            $result = $this->expressionManager->process($expression, $context, $this);
            if ($result !== null) {
                return $result;
            }
            
            // 默认返回占位符
            return $matches[0];
        }, $template);
    }
    
    /**
     * 注册默认的表达式处理器
     */
    protected function registerDefaultHandlers(): void
    {
        // 注册默认处理器
        $this->expressionManager->register(new ObjectMethodHandler());
        $this->expressionManager->register(new ObjectPropertyHandler());
        $this->expressionManager->register(new MultiArrayHandler());
        $this->expressionManager->register(new ArrayItemHandler());
        $this->expressionManager->register(new ContextVariableHandler());
    }
    
    /**
     * 注册自定义表达式处理器
     * 
     * @param OperationLogExpressionHandlerInterface $handler
     * @return self
     */
    public function registerHandler(OperationLogExpressionHandlerInterface $handler): self
    {
        $this->expressionManager->register($handler);
        return $this;
    }
    
    /**
     * 移除表达式处理器
     *
     * @param string $name
     * @return self
     */
    public function removeHandler(string $name): self
    {
        $this->expressionManager->remove($name);
        return $this;
    }
    
    /**
     * 获取表达式处理器管理器
     *
     * @return OperationLogExpressionManager
     */
    public function getExpressionManager(): OperationLogExpressionManager
    {
        return $this->expressionManager;
    }

    /**
     * 从上下文或容器中获取对象
     * 
     * @param string $key 对象键名
     * @param array $context 上下文数据
     * @return object|null 对象实例或null
     */
    public function getObjectFromContextOrContainer(string $key, array $context): ?object
    {
        // 如果上下文中有该对象，直接返回
        if (isset($context[$key]) && is_object($context[$key])) {
            return $context[$key];
        }
        
        // 尝试从容器中获取
        $container = ApplicationContext::getContainer();
        $map = $this->operationLogMap[$key] ?? '';
        
        if (!empty($map) && $container->has($map)) {
            return $container->get($map);
        }

        return null;
    }

    /**
     * 解析方法调用的参数
     * 
     * @param string $argsString 参数字符串
     * @return array 参数数组
     */
    public function parseArguments(string $argsString): array
    {
        // 按逗号分割参数，并去除多余空格
        $args = array_map('trim', explode(',', $argsString));
        return array_filter($args, fn ($arg) => $arg !== '');
    }

    /**
     * 调用对象的方法
     * 
     * @param object $object 对象
     * @param string $method 方法名
     * @param array $args 方法参数
     * @return mixed|null 方法调用结果
     */
    public function callObjectMethod(object $object, string $method, array $args)
    {
        if (method_exists($object, $method)) {
            return call_user_func_array([$object, $method], $args);
        }
        return null;
    }

    /**
     * 获取对象的属性值，包括私有和受保护属性
     * 
     * @param object $object 对象
     * @param string $property 属性名
     * @return mixed|null 属性值
     */
    public function getObjectProperty(object $object, string $property)
    {
        try {
            $reflection = new \ReflectionClass($object);

            if ($reflection->hasProperty($property)) {
                $reflectionProperty = $reflection->getProperty($property);
                $reflectionProperty->setAccessible(true);
                return $reflectionProperty->getValue($object);
            }
            
            // 尝试通过getter方法获取
            $getterMethod = 'get' . ucfirst($property);
            if (method_exists($object, $getterMethod)) {
                return $object->$getterMethod();
            }
        } catch (\ReflectionException $e) {
            // 反射异常，静默处理
        }
        
        return null;
    }
} 