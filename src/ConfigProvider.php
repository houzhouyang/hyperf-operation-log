<?php

declare(strict_types=1);

namespace HyperfOperationLog;

use HyperfOperationLog\Contracts\OperationLogStorageInterface;
use HyperfOperationLog\Contracts\UserProviderInterface;
use HyperfOperationLog\Listeners\AsyncOperationLogListener;
use HyperfOperationLog\Listeners\OperationLogSaveListener;  
use HyperfOperationLog\Services\DefaultUserProvider;
use HyperfOperationLog\Storage\DatabaseStorage;
use HyperfOperationLog\Storage\RedisStorage;
use Psr\Container\ContainerInterface;
use Hyperf\Contract\ConfigInterface;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            // 合并到  config/autoload/dependencies.php 文件
            'dependencies' => [
                // 在这里定义对应的依赖关系，仅支持PSR-11
                UserProviderInterface::class => function (ContainerInterface $container) {
                    // 检查是否有自定义用户提供者配置
                    $config = $container->get(ConfigInterface::class);
                    $userProviderClass = $config->get('operation_log.user_provider');
                    if ($userProviderClass && class_exists($userProviderClass)) {
                        return $container->get($userProviderClass);
                    }
                    return $container->get(DefaultUserProvider::class);
                },
                OperationLogStorageInterface::class => function (ContainerInterface $container) {
                    // 获取存储策略配置
                    $config = $container->get(ConfigInterface::class);
                    $type = $config->get('operation_log.storage.type', 'database');
                    
                    switch ($type) {
                        case 'redis':
                            return new RedisStorage($container);
                        case 'database':
                        default:
                            return new DatabaseStorage($container);
                    }
                },
            ],
            // 合并到  config/autoload/annotations.php 文件
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                    'collectors' => [
                    ],
                ],
            ],
            // 默认 Command 的定义，合并到 Hyperf\Contract\ConfigInterface 内
            'commands' => [
                // 批量导入命令
                \HyperfOperationLog\Command\ImportLogsFromRedisCommand::class,
            ],
            // 监听器配置
            'listeners' => function (ContainerInterface $container) {
                $config = $container->get(ConfigInterface::class);
                //是否异步
                $useAsync = (bool) $config->get('operation_log.async.enable', false);
                return [
                    $container->get($useAsync 
                        ? AsyncOperationLogListener::class 
                        : OperationLogSaveListener::class
                    )
                ];
            },
            // 组件默认配置文件
            'publish' => [
                [
                    'id' => 'config',
                    'description' => '发布操作日志组件配置文件',
                    'source' => __DIR__ . '/../publish/operation_log.php',
                    'destination' => function ($basePath) {
                        return $basePath . '/config/autoload/operation_log.php';
                    },
                ],
                [
                    'id' => 'migrations',
                    'description' => '发布操作日志组件数据库迁移文件',
                    'source' => __DIR__ . '/../migrations/create_operation_logs_table.php',
                    'destination' => function ($basePath) {
                        return $basePath . '/migrations/2023_01_01_000001_create_operation_logs_table.php';
                    },
                ],
            ],
            // 亦可继续定义其它配置，最终都会合并到与 ConfigInterface 对应的配置储存库中
        ];
    }
}