<?php

declare(strict_types=1);

return [
    /**
     * 操作日志解析服务映射
     * 
     * 用于模板中 (obj) 标识的对象查找
     * 
     * 示例：
     * 'userService' => \App\Service\UserService::class,
     */
    'service_map' => [
        // 这里可以添加你的服务映射
    ],
    
    /**
     * 数据库连接配置
     */
    'database' => [
        // 数据库连接池，默认使用 default
        'connection' => env('DB_CONNECTION', 'default'),
        
        // 操作日志表名，默认为 operation_logs
        'table' => env('OPERATION_LOG_TABLE', 'operation_logs'),
    ],
    
    /**
     * 自定义监听器
     */
    'custom_listener' => null,

    /**
     * 存储配置
     */
    'storage' => [
        // 存储类型：database(数据库)或redis
        'type' => env('OPERATION_LOG_STORAGE', 'database'),
        
        // Redis存储相关配置
        'redis' => [
            // 键名前缀
            'key_prefix' => 'operation_log:',
            
            // 过期时间（秒），默认7天
            'expire' => 604800,
            
            // 仅测试时设为true，使日志立即过期
            'expire_immediately' => false,
        ],
    ],

    /**
     * 异步队列配置
     */
    'async' => [
        // 是否启用异步日志记录
        'enable' => env('OPERATION_LOG_ASYNC', false),
        
        // 使用的队列名称
        'queue' => 'default',
    ],
    
    /**
     * 配置要跳过记录日志的请求路径
     */
    'except_paths' => [
        '/health-check',
        '/favicon.ico',
    ],
    
    /**
     * 配置要跳过记录日志的请求方法
     */
    'except_methods' => [
        'OPTIONS',
    ],
    
    /**
     * 日志记录策略
     */
    'recording' => [
        // 是否只记录成功的操作
        'only_success' => env('OPERATION_LOG_ONLY_SUCCESS', true),
        
        // 自定义成功响应码，支持多个
        'success_codes' => [
            1000,  // 默认成功码
            200,   // HTTP成功状态码
            // 可自行添加其他成功码
        ],
        
        // 状态码字段配置，按顺序查找
        'code_fields' => ['code'],
    ],
    
    /**
     * 用户提供者配置
     * 
     * 可以设置自定义的用户提供者实现，需实现 UserProviderInterface 接口
     * 如果不设置，默认使用 DefaultUserProvider
     */
    'user_provider' => null, // 例如：\App\Services\CustomUserProvider::class,
    
    /**
     * 自定义表达式处理器
     * 
     * 可以添加自定义表达式处理器，需实现 OperationLogExpressionHandlerInterface 接口
     * 这些处理器将自动注册到解析服务中
     */
    'expression_handlers' => [
        // 不启用默认处理器列表（如果为true，则不加载下面指定的默认处理器）
        'disable_default' => false,
        
        // 默认处理器，可以选择性地禁用某些处理器
        'default' => [
            \HyperfOperationLog\ExpressionHandlers\ObjectMethodHandler::class => true,
            \HyperfOperationLog\ExpressionHandlers\ObjectPropertyHandler::class => true,
            \HyperfOperationLog\ExpressionHandlers\MultiArrayHandler::class => true,
            \HyperfOperationLog\ExpressionHandlers\ArrayItemHandler::class => true,
            \HyperfOperationLog\ExpressionHandlers\ContextVariableHandler::class => true,
        ],
        
        // 自定义处理器
        'custom' => [
            // 示例：日期格式化处理器
            \HyperfOperationLog\ExpressionHandlers\DateFormatHandler::class,
            
            // 可以添加更多自定义处理器
            // \App\ExpressionHandlers\YourCustomHandler::class,
        ],
    ],

    /**
     * 认证管理器类名或服务标识符
     * 支持标准的 auth.manager 或 Qbhy\HyperfAuth\AuthManager 等
     */
    'auth_manager_class' => 'Qbhy\HyperfAuth\AuthManager',

    /**
     * 用户属性映射配置
     * 可以是单个字符串或字符串数组
     * 当使用单个字符串时（如'uid'），会自动尝试：
     * 1. 调用 getUid() 方法
     * 2. 调用 uid() 方法
     * 3. 访问 $user->uid 属性
     * 4. 访问 $user['uid'] 数组键
     */
    'user_attributes' => [
        'uid' => 'uid', // 用户ID字段
        'userName' => 'userName', // 用户名字段
        'organizationCode' => 'organizationCode', // 组织代码字段
        // 可以添加其他自定义属性
    ],
]; 