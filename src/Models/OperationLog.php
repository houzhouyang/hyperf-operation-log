<?php

declare(strict_types=1);

namespace HyperfOperationLog\Models;

use Hyperf\Database\Model\Model;
use Hyperf\Database\Model\Events\Creating;
use Hyperf\DbConnection\Model\Model as DbModel;
use Hyperf\Context\ApplicationContext;
use Hyperf\HttpServer\Contract\RequestInterface;

class OperationLog extends DbModel
{
    /**
     * 数据表名称
     */
    protected  $table = 'operation_logs';

    /**
     * 可批量赋值的属性
     */
    protected $fillable = [
        'biz_no',
        'content',
        'category',
        'action',
        'user_name',
        'user_id',
        'organization_code',
        'request_data',
        'response_data',
        'extra_params',
        'ip',
        'user_agent',
    ];

    /**
     * 类型转换
     */
    protected $casts = [
        'request_data' => 'json',
        'response_data' => 'json',
        'extra_params' => 'json',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * 创建时自动添加IP和UserAgent
     */
    public function creating(Creating $event)
    {
        try {
            if (empty($this->ip) && ApplicationContext::hasContainer()) {
                $container = ApplicationContext::getContainer();
                if ($container->has(RequestInterface::class)) {
                    $request = $container->get(RequestInterface::class);
                    $this->ip = $request->getServerParams()['remote_addr'] ?? '0.0.0.0';
                    
                    if (empty($this->user_agent)) {
                        $this->user_agent = $request->getHeaderLine('User-Agent') ?? '';
                    }
                }
            }
        } catch (\Throwable $e) {
            // 忽略异常
        }
    }
} 