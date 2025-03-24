<?php

declare(strict_types=1);

namespace HyperfOperationLog\Storage;

use HyperfOperationLog\Contracts\OperationLogStorageInterface;
use HyperfOperationLog\Events\OperationLogCreatedEvent;
use HyperfOperationLog\Services\ConfigAdapter;
use Hyperf\Redis\Redis;
use Psr\Log\LoggerInterface;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Hyperf\Contract\ConfigInterface;

class RedisStorage implements OperationLogStorageInterface
{
    /**
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * @var Redis
     */
    protected Redis $redis;

    /**
     * @var LoggerInterface|null
     */
    protected ?LoggerInterface $logger = null;

    /**
     * @var ConfigInterface
     */
    protected ConfigInterface $config;
    
    /**
     * @var ConfigAdapter
     */
    protected ConfigAdapter $configAdapter;

    /**
     * Redis键名前缀
     */
    protected string $keyPrefix = 'operation_log:';

    /**
     * 是否立即过期（用于测试）
     */
    protected bool $expireImmediately = false;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->redis = $container->get(Redis::class);
        $this->config = $container->get(ConfigInterface::class);
        $this->configAdapter = new ConfigAdapter($this->config);
        
        if ($container->has(LoggerFactory::class)) {
            $this->logger = $container->get(LoggerFactory::class)->get('operation-log');
        }
        
        // 从配置获取前缀
        $redisConfig = $this->configAdapter->get('operation_log.storage.redis', []);
        if (!empty($redisConfig['key_prefix'])) {
            $this->keyPrefix = $redisConfig['key_prefix'];
        }
        
        // 是否立即过期（仅用于测试）
        $this->expireImmediately = $redisConfig['expire_immediately'] ?? false;
    }

    /**
     * 存储操作日志到Redis
     * 
     * @param OperationLogCreatedEvent $event 操作日志事件
     */
    public function store(OperationLogCreatedEvent $event): void
    {
        try {
            $id = uniqid('log_', true);
            $key = $this->keyPrefix . $id;
            
            $data = [
                'id' => $id,
                'biz_no' => $event->bizNo,
                'content' => $event->content,
                'category' => $event->category,
                'action' => $event->action,
                'user_name' => $event->userName,
                'user_id' => $event->userId,
                'organization_code' => $event->organizationCode,
                'request_data' => json_encode($event->requestData),
                'response_data' => json_encode($event->responseData),
                'extra_params' => json_encode($event->extraParams),
                'created_at' => date('Y-m-d H:i:s'),
            ];
            
            // 将日志保存到Redis
            $this->redis->hMSet($key, $data);
            
            // 添加到集合，便于批量处理
            $this->redis->sAdd($this->keyPrefix . 'pending', $id);
            
            // 设置过期时间，防止数据永久存在
            $expireTime = $this->expireImmediately ? 1 : (int)$this->configAdapter->get('operation_log.storage.redis.expire', 604800); // 默认7天
            $this->redis->expire($key, $expireTime);
            
        } catch (\Throwable $e) {
            if ($this->logger) {
                $this->logger->error('Redis保存操作日志失败', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => [
                        'biz_no' => $event->bizNo,
                        'content' => $event->content,
                        'category' => $event->category,
                        'action' => $event->action,
                    ],
                ]);
            }
        }
    }
} 