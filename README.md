# Hyperf Operation Log

一个优雅的基于注解的操作日志记录组件，适用于Hyperf框架。

## 特性

- 基于注解的操作日志记录，侵入性低
- 支持模板语法解析，灵活配置日志内容
- 支持对象属性和方法调用
- 支持记录请求和响应数据
- 支持通过事件机制自定义操作日志处理
- 支持查询接口，方便获取操作日志
- 支持自动使用类名和方法名作为日志类别和动作的默认值
- 支持自定义用户提供者，灵活适配不同的用户认证系统
- 支持多种存储策略（数据库、Redis）
- 支持同步和异步处理，提升性能

## 安装

```bash
composer require houzhouyang/hyperf-operation-log
```

发布配置文件和数据库迁移文件：

```bash
php bin/hyperf.php vendor:publish houzhouyang/hyperf-operation-log
```

执行数据库迁移：

```bash
php bin/hyperf.php migrate
```

如果需要使用Redis存储或异步队列功能，可以安装以下可选依赖：

```bash
# Redis存储
composer require hyperf/redis

# 异步队列
composer require hyperf/async-queue
```

## 使用方法

### 1. 使用注解记录操作日志

在需要记录操作日志的方法上添加 `@OperationLog` 注解：

```php
<?php

namespace App\Controller;

use HyperfOperationLog\Annotation\OperationLog;

class OrderController
{
    /**
     * 更新订单
     * 
     * #[OperationLog(
     *     content: "用户{authUser.realName}编辑了订单{param.0}",
     *     category: "订单管理",
     *     action: "编辑",
     *     bizNo: "{param.0}"
     * )]
     */
    #[OperationLog(
        content: "用户{authUser.realName}编辑了订单{param.0}",
        category: "订单管理",
        action: "编辑",
        bizNo: "{param.0}"
    )]
    public function update(int $id, UpdateOrderRequest $request)
    {
        // 业务逻辑
        return ['code' => 1000, 'message' => '成功', 'data' => []];
    }
    
    /**
     * 使用默认类名和方法名作为category和action
     * 
     * #[OperationLog(
     *     content: "用户{authUser.realName}删除了订单{param.0}",
     *     bizNo: "{param.0}"
     * )]
     */
    #[OperationLog(
        content: "用户{authUser.realName}删除了订单{param.0}",
        bizNo: "{param.0}"
    )]
    public function delete(int $id)
    {
        // 业务逻辑
        return ['code' => 1000, 'message' => '成功', 'data' => []];
    }
}
```

### 2. 模板语法

支持以下模板语法：

- `{param.0}`：第一个参数
- `{param.1}`：第二个参数
- `{param.name}`：参数数组中的name属性
- `{param.arr.id}`：参数数组中所有元素的id属性，将返回一个JSON数组
- `{authUser.realName}`：当前认证用户的realName属性
- `{(obj)userService.getUsername(1)}`：调用容器中的userService服务的getUsername方法
- `{(obj)orderService.status}`：获取容器中的orderService服务的status属性
- `{logContext}`：从上下文中获取的自定义数据

### 3. 附加上下文数据

在业务逻辑中，可以添加自定义上下文数据：

```php
<?php

use HyperfOperationLog\OperationLogContext;

// 单条数据
OperationLogContext::addLogContextData('productName', '产品A');

// 或者批量数据
OperationLogContext::setLogContextData([
    'oldStatus' => 'pending',
    'newStatus' => 'completed',
    'products' => [1, 2, 3]
]);
```

### 4. 查询操作日志

```php
<?php

use HyperfOperationLog\Services\OperationLogQueryService;

class OperationLogController
{
    /**
     * @var OperationLogQueryService
     */
    private $operationLogQueryService;
    
    public function __construct(OperationLogQueryService $operationLogQueryService)
    {
        $this->operationLogQueryService = $operationLogQueryService;
    }
    
    /**
     * 获取操作日志列表
     */
    public function index()
    {
        $conditions = [
            'category' => '订单管理',
            'user_name' => '张三',
            'organization_code' => 'ORG001', // 按组织编码查询
            'start_time' => '2023-01-01 00:00:00',
            'end_time' => '2023-12-31 23:59:59',
        ];
        
        $page = 1;
        $pageSize = 20;
        
        $result = $this->operationLogQueryService->getList($conditions, $page, $pageSize);
        
        return [
            'code' => 1000,
            'message' => '成功',
            'data' => $result
        ];
    }
    
    /**
     * 获取指定业务单号的操作日志
     */
    public function getByBizNo(string $bizNo)
    {
        $result = $this->operationLogQueryService->getListByBizNo($bizNo);
        
        return [
            'code' => 1000,
            'message' => '成功',
            'data' => $result
        ];
    }
}
```

## 配置

配置文件 `config/autoload/operation_log.php`：

```php
<?php

return [
    /**
     * 操作日志解析服务映射
     */
    'service_map' => [
        'userService' => \App\Service\UserService::class,
        'orderService' => \App\Service\OrderService::class,
    ],
    
    /**
     * 数据库连接配置
     */
    'database' => [
        // 数据库连接池
        'connection' => env('DB_CONNECTION', 'default'),
        
        // 操作日志表名
        'table' => env('OPERATION_LOG_TABLE', 'operation_logs'),
    ],
    
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
            0,     // 常见成功码
            200,   // HTTP状态码
            // 可自行添加其他成功码
        ],
    ],
    
    /**
     * 用户提供者配置
     * 
     * 可以设置自定义的用户提供者实现，需实现 UserProviderInterface 接口
     * 如果不设置，默认使用 DefaultUserProvider
     */
    'user_provider' => null, // 例如：\App\Services\CustomUserProvider::class,
    
    /**
     * 认证管理器类名或服务标识符
     * 支持标准的 auth.manager 或自定义认证管理器
     */
    'auth_manager_class' => 'auth.manager',
    
    /**
     * 用户属性映射配置
     * 可以是单个字符串或字符串数组
     * 指定如何从用户对象中获取属性
     */
    'user_attributes' => [
        'uid' => 'uid', // 用户ID字段
        'userName' => 'realName', // 用户名字段
        'organizationCode' => 'organizationCode', // 组织代码字段
        // 可以添加其他自定义属性
    ],
];
```

### 自定义用户认证系统

组件支持多种框架的认证系统，您可以根据自己项目需要配置：

#### 1. 使用 auth.manager 标准认证管理器

这是默认配置，适用于使用标准认证系统的项目：

```php
'auth_manager_class' => 'auth.manager',
```

#### 2. 使用 qbhy/hyperf-auth 包

如果您的项目使用了 qbhy/hyperf-auth 包进行认证，可以这样配置：

```php
'auth_manager_class' => 'Qbhy\HyperfAuth\AuthManager',
```

#### 3. 用户属性映射

您可以自定义如何从用户对象中获取用户ID、用户名等属性：

```php
'user_attributes' => [
    'uid' => 'id', // 将从 getId()、id 属性或 ['id'] 获取用户ID
    'userName' => ['nickname', 'name', 'username'], // 按优先级尝试获取
    'organizationCode' => 'org_code', // 将从 getOrgCode()、org_code 属性或 ['org_code'] 获取
],
```

当配置单个属性如 'id' 时，系统会自动尝试：
1. 调用 getId() 方法
2. 调用 id() 方法  
3. 访问对象的 id 属性
4. 以数组方式访问 ['id'] 键值

这种灵活的配置方式使组件能够适应不同的用户模型结构。

## 自定义用户提供者

不同的系统可能有不同的用户认证机制。为了灵活适配各种系统，你可以自定义实现用户提供者：

```php
<?php

namespace App\Services;

use HyperfOperationLog\Contracts\UserProviderInterface;

class CustomUserProvider implements UserProviderInterface
{
    /**
     * 获取当前操作用户信息
     * 
     * @return array 包含用户信息的数组
     */
    public function getCurrentUser(): array
    {
        // 根据你的系统认证机制，获取当前用户信息
        $user = YourAuthSystem::getCurrentUser();
        
        // 返回一个包含用户信息的数组，至少包含以下字段
        return [
            'uid' => $user->getId(),
            'realName' => $user->getName(),
            'organizationCode' => $user->getOrganizationCode(), // 组织编码，用于区分不同组织的日志
            // 可以添加其他字段
        ];
    }
}
```

## 自定义操作日志处理

默认情况下，操作日志通过监听器写入数据库。如果需要自定义处理，可以创建自己的监听器：

```php
<?php

namespace App\Listener;

use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\Event\Annotation\Listener;
use HyperfOperationLog\Events\OperationLogCreatedEvent;

#[Listener]
class CustomOperationLogListener implements ListenerInterface
{
    public function listen(): array
    {
        return [
            OperationLogCreatedEvent::class,
        ];
    }

    public function process(object $event): void
    {
        if (!$event instanceof OperationLogCreatedEvent) {
            return;
        }
        
        // 自定义处理逻辑，例如发送到消息队列、推送通知等
    }
}
```

## 存储策略

组件支持多种存储策略，通过配置文件可以灵活切换：

### 数据库存储（默认）

直接将操作日志写入数据库。适用于普通流量的应用。

```php
// config/autoload/operation_log.php
'storage' => [
    'type' => 'database',
],
```

### Redis存储

将操作日志先写入Redis，适用于高流量应用，可以通过计划任务定期导入到数据库。

```php
// config/autoload/operation_log.php
'storage' => [
    'type' => 'redis',
    'redis' => [
        'key_prefix' => 'operation_log:',
        'expire' => 604800, // 7天
    ],
],
```

使用命令行工具将Redis中的日志导入到数据库：

```bash
# 导入所有日志，处理后仍保留在Redis中
php bin/hyperf.php operation-log:import-from-redis

# 导入所有日志，处理后从Redis删除
php bin/hyperf.php operation-log:import-from-redis --remove

# 导入指定数量的日志
php bin/hyperf.php operation-log:import-from-redis --limit=1000 --remove
```

## 异步处理

启用异步处理可以显著提升性能，避免日志记录影响主流程：

```php
// config/autoload/operation_log.php
'async' => [
    'enable' => true,
    'queue' => 'default', // 使用的队列名称
],
```

确保已经启动了队列消费进程：

```bash
php bin/hyperf.php queue:consume
```

## 日志记录策略

您可以通过配置控制日志记录的行为：

### 仅记录成功操作

默认情况下，组件只会记录成功的操作。您可以通过配置更改此行为：

```php
// config/autoload/operation_log.php
'recording' => [
    // 设置为 false 可以记录所有操作，包括失败的操作
    'only_success' => false,
],
```

也可以通过环境变量控制：

```dotenv
OPERATION_LOG_ONLY_SUCCESS=false
```

### 自定义成功响应码

组件支持自定义成功响应码，默认情况下包含常见的成功码：

```php
// config/autoload/operation_log.php
'recording' => [
    'success_codes' => [
        1000,  // 默认成功码
        0,     // 常见成功码
        200,   // HTTP状态码
        // 添加您系统中其他表示成功的响应码
    ],
],
```

组件会检查返回的响应中的 `code` 或 `status` 字段，如果匹配任何一个成功码，则记录日志。

## 注意事项

### 配置获取

在Hyperf 3.0中，直接使用`config()`函数已被废弃。请使用`ConfigInterface`代替：

```php
// 旧方式 (已废弃)
$value = config('operation_log.some_config', 'default_value');

// 新方式
// 依赖注入
public function __construct(
    private ConfigInterface $config
) {
}

// 使用
$value = $this->config->get('operation_log.some_config', 'default_value');

// 如果在闭包中使用
function (ContainerInterface $container) {
    $config = $container->get(ConfigInterface::class);
    $value = $config->get('operation_log.some_config', 'default_value');
}
```

## License

MIT