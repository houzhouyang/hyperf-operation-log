<?php

declare(strict_types=1);

namespace HyperfOperationLog\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Redis\Redis;
use HyperfOperationLog\Models\OperationLog;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Contract\ConfigInterface;
use HyperfOperationLog\Services\ConfigAdapter;

#[Command]
class ImportLogsFromRedisCommand extends HyperfCommand
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
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;
    
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
     * 每批处理的日志数量
     */
    protected int $batchSize = 100;
    
    /**
     * 批量导入异常时的重试次数
     */
    protected int $maxRetries = 3;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct('operation-log:import-from-redis');
        
        $this->container = $container;
        $this->redis = $container->get(Redis::class);
        $this->logger = $container->get(LoggerFactory::class)->get('operation-log');
        $this->config = $container->get(ConfigInterface::class);
        $this->configAdapter = new ConfigAdapter($this->config);
        
        // 从配置获取前缀
        $redisConfig = $this->configAdapter->get('operation_log.storage.redis', []);
        if (!empty($redisConfig['key_prefix'])) {
            $this->keyPrefix = $redisConfig['key_prefix'];
        }
        
        // 从配置获取批处理大小
        if (!empty($redisConfig['batch_size'])) {
            $this->batchSize = (int)$redisConfig['batch_size'];
        }
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('从Redis导入操作日志到数据库');
        
        $this->addOption(
            'limit',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL,
            '处理的最大日志数量',
            0
        );
        
        $this->addOption(
            'remove',
            null,
            \Symfony\Component\Console\Input\InputOption::VALUE_NONE,
            '处理完成后移除Redis中的日志'
        );
    }

    public function handle()
    {
        $this->line('开始从Redis导入操作日志到数据库...');
        
        // 获取待处理的日志ID列表
        $pendingKey = $this->keyPrefix . 'pending';
        $pendingCount = $this->redis->sCard($pendingKey);
        
        if ($pendingCount === 0) {
            $this->info('没有待处理的日志。');
            return 0;
        }
        
        $this->info("找到 {$pendingCount} 条待处理的日志。");
        
        // 获取用户设置的处理上限
        $limit = (int)$this->input->getOption('limit');
        if ($limit > 0 && $limit < $pendingCount) {
            $this->info("将处理前 {$limit} 条日志。");
            $pendingCount = $limit;
        }
        
        // 是否处理后移除
        $shouldRemove = $this->input->getOption('remove');
        if ($shouldRemove) {
            $this->info('处理完成后将从Redis移除已处理的日志。');
        } else {
            $this->info('处理完成后不会从Redis移除日志，你可以使用 --remove 选项来启用移除功能。');
        }
        
        // 开始处理
        $processedCount = 0;
        $errorCount = 0;
        $progress = $this->output->createProgressBar($pendingCount);
        
        // 分批处理
        $batches = ceil($pendingCount / $this->batchSize);
        
        for ($i = 0; $i < $batches; $i++) {
            $batchLimit = min($this->batchSize, $pendingCount - $processedCount);
            if ($batchLimit <= 0) {
                break;
            }
            
            // 获取一批ID
            $batchIds = $this->redis->sRandMember($pendingKey, $batchLimit);
            if (empty($batchIds)) {
                break;
            }
            
            $logsToStore = [];
            $processedIds = [];
            
            // 读取日志数据
            foreach ($batchIds as $id) {
                $key = $this->keyPrefix . $id;
                $logData = $this->redis->hGetAll($key);
                
                if (empty($logData)) {
                    $this->logger->warning("找不到日志 ID: {$id}");
                    $processedIds[] = $id;
                    $errorCount++;
                    continue;
                }
                
                try {
                    // 解码JSON字段
                    $logData['request_data'] = json_decode($logData['request_data'] ?? '{}', true) ?: [];
                    $logData['response_data'] = json_decode($logData['response_data'] ?? '{}', true) ?: [];
                    $logData['extra_params'] = json_decode($logData['extra_params'] ?? '{}', true) ?: [];
                    
                    // 转换字段名称
                    $logsToStore[] = [
                        'biz_no' => $logData['biz_no'] ?? '',
                        'content' => $logData['content'] ?? '',
                        'category' => $logData['category'] ?? '',
                        'action' => $logData['action'] ?? '',
                        'user_name' => $logData['user_name'] ?? '',
                        'user_id' => $logData['user_id'] ?? '',
                        'organization_code' => $logData['organization_code'] ?? null,
                        'request_data' => $logData['request_data'],
                        'response_data' => $logData['response_data'],
                        'extra_params' => $logData['extra_params'],
                        'created_at' => $logData['created_at'] ?? date('Y-m-d H:i:s'),
                    ];
                    
                    $processedIds[] = $id;
                } catch (\Throwable $e) {
                    $this->logger->error("解析日志数据失败: {$id}", [
                        'error' => $e->getMessage(),
                        'data' => $logData
                    ]);
                    $errorCount++;
                }
            }
            
            // 批量保存到数据库
            if (!empty($logsToStore)) {
                $retries = 0;
                $success = false;
                
                while (!$success && $retries < $this->maxRetries) {
                    try {
                        OperationLog::insert($logsToStore);
                        $success = true;
                    } catch (\Throwable $e) {
                        $retries++;
                        $this->logger->error("批量插入日志失败 (尝试 {$retries}/{$this->maxRetries})", [
                            'error' => $e->getMessage(),
                            'count' => count($logsToStore)
                        ]);
                        
                        if ($retries >= $this->maxRetries) {
                            // 最后一次尝试失败，增加错误计数
                            $errorCount += count($logsToStore);
                        } else {
                            // 等待一小段时间后重试
                            usleep(500000); // 0.5秒
                        }
                    }
                }
            }
            
            // 从Redis移除已处理的日志
            if ($shouldRemove && !empty($processedIds)) {
                foreach ($processedIds as $id) {
                    $key = $this->keyPrefix . $id;
                    $this->redis->del($key);
                    $this->redis->sRem($pendingKey, $id);
                }
            }
            
            $processedCount += count($processedIds);
            $progress->advance(count($processedIds));
        }
        
        $progress->finish();
        $this->newLine(2);
        
        // 输出处理结果
        $this->info("导入完成: 成功 {$processedCount} 条, 失败 {$errorCount} 条。");
        
        return 0;
    }
} 