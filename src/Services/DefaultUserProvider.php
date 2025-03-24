<?php

declare(strict_types=1);

namespace HyperfOperationLog\Services;

use HyperfOperationLog\Contracts\UserProviderInterface;
use Psr\Container\ContainerInterface;
use Hyperf\Contract\ConfigInterface;

class DefaultUserProvider implements UserProviderInterface
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;
    
    /**
     * @var ConfigInterface
     */
    protected ConfigInterface $config;
    
    /**
     * @var mixed 认证管理器实例
     */
    protected $authManager;
    
    /**
     * @var array 默认用户属性映射
     */
    protected array $defaultUserAttributes = [
        'uid' => ['uid'],
        'userName' => ['userName'],
        'organizationCode' => ['organizationCode'],
    ];

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->config = $container->get(ConfigInterface::class);
        
        // 从配置中获取认证管理器类名或服务标识符
        $authManagerClass = $this->config->get('operation_log.auth_manager_class', 'auth.manager');
        
        // 自定义用户属性映射
        $customAttributes = $this->config->get('operation_log.user_attributes', []);
        if (!empty($customAttributes) && is_array($customAttributes)) {
            foreach ($customAttributes as $key => $attribute) {
                if (is_string($attribute)) {
                    $this->defaultUserAttributes[$key] = [$attribute];
                } elseif (is_array($attribute)) {
                    $this->defaultUserAttributes[$key] = $attribute;
                }
            }
        }
        
        // 尝试获取认证管理器实例
        if ($this->container->has($authManagerClass)) {
            $this->authManager = $this->container->get($authManagerClass);
        }
    }

    /**
     * 获取当前操作用户信息
     * 
     * @return array 包含用户信息的数组，至少包含 id, name, organizationCode 等字段
     */
    public function getCurrentUser(): array
    {
        // 如果没有配置认证管理器或无法获取，返回空数组
        if (!$this->authManager) {
            return [
                'uid' => 0,
                'userName' => '未知用户',
                'organizationCode' => '',
            ];
        }
        
        try {
            $userObject = $this->getUserFromAuthManager();
            
            if (!$userObject) {
                return [
                    'uid' => 0,
                    'userName' => '未知用户',
                    'organizationCode' => '',
                ];
            }
            
            // 构建用户信息数组
            $userInfo = [];
            
            // 处理所有配置的属性
            foreach ($this->defaultUserAttributes as $key => $attributes) {
                $value = $this->smartExtractValue($userObject, $attributes);
                $userInfo[$key] = $value ?? '';
            }
            
            return $userInfo;
        } catch (\Throwable $e) {
            // 记录异常信息
            if ($this->container->has(\Psr\Log\LoggerInterface::class)) {
                $logger = $this->container->get(\Psr\Log\LoggerInterface::class);
                $logger->warning('Failed to get user: ' . $e->getMessage());
            }
            
            return [
                'uid' => 0,
                'userName' => '未知用户',
                'organizationCode' => '',
            ];
        }
    }
    
    /**
     * 从认证管理器获取用户对象
     * 
     * @return mixed 用户对象或null
     */
    protected function getUserFromAuthManager()
    {
        if (!is_object($this->authManager)) {
            return null;
        }
        
        $className = get_class($this->authManager);
        
        // 支持 qbhy/hyperf-auth 包
        if ($className === 'Qbhy\HyperfAuth\AuthManager') {
            return $this->authManager->guard()->user();
        } 
        
        // 尝试调用 user 方法
        if (method_exists($this->authManager, 'user')) {
            return $this->authManager->user();
        }
        
        // 无法获取用户
        return null;
    }
    
    /**
     * 智能提取属性值
     * 
     * @param mixed $object 对象或数组
     * @param array $attributes 属性名称列表
     * @return mixed 属性值或null
     */
    protected function smartExtractValue($object, array $attributes)
    {
        if (empty($object)) {
            return null;
        }
        
        foreach ($attributes as $attribute) {
            $value = $this->extractSingleAttribute($object, $attribute);
            if ($value !== null) {
                return $value;
            }
        }
        
        return null;
    }
    
    /**
     * 从对象或数组中提取单个属性
     * 
     * @param mixed $source 数据源（对象或数组）
     * @param string $attribute 属性名
     * @return mixed 属性值或null
     */
    protected function extractSingleAttribute($source, string $attribute)
    {
        // 1. 尝试直接方法调用 (get + 大写首字母 + 剩余部分)
        $getterMethod = 'get' . ucfirst($attribute);
        if (is_object($source) && method_exists($source, $getterMethod)) {
            return $source->{$getterMethod}();
        }
        
        // 2. 尝试直接方法调用（属性名作为方法）
        if (is_object($source) && method_exists($source, $attribute)) {
            return $source->{$attribute}();
        }
        
        // 3. 尝试对象属性访问
        if (is_object($source) && property_exists($source, $attribute)) {
            return $source->{$attribute};
        }
        
        // 4. 尝试通过魔术方法 __get 获取（如Eloquent模型）
        if (is_object($source) && method_exists($source, '__get')) {
            try {
                return $source->{$attribute};
            } catch (\Throwable $e) {
                // 忽略错误
            }
        }
        
        // 5. 尝试数组访问
        if (is_array($source) && isset($source[$attribute])) {
            return $source[$attribute];
        }
        
        // 6. 尝试ArrayAccess接口
        if (is_object($source) && $source instanceof \ArrayAccess && isset($source[$attribute])) {
            return $source[$attribute];
        }
        
        return null;
    }
} 