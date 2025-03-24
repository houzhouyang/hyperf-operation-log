<?php

declare(strict_types=1);

namespace HyperfOperationLog\Services;

use HyperfOperationLog\Models\OperationLog;
use Hyperf\Database\Model\Builder;

class OperationLogQueryService
{
    /**
     * 查询操作日志列表
     * 
     * @param array $conditions 查询条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array 查询结果
     */
    public function getList(array $conditions = [], int $page = 1, int $pageSize = 20): array
    {
        $query = OperationLog::query();
        
        // 应用查询条件
        $this->applyConditions($query, $conditions);
        
        // 排序
        $query->orderBy('created_at', 'desc');
        
        // 分页查询
        $total = $query->count();
        $list = $query->forPage($page, $pageSize)->get();
        
        return [
            'total' => $total,
            'list' => $list,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }
    
    /**
     * 获取单条操作日志详情
     * 
     * @param int $id 日志ID
     * @return OperationLog|null 日志详情
     */
    public function getDetail(int $id): ?OperationLog
    {
        return OperationLog::find($id);
    }
    
    /**
     * 获取指定业务单号的操作日志列表
     * 
     * @param string $bizNo 业务编号
     * @param array $conditions 额外查询条件
     * @param int $page 页码
     * @param int $pageSize 每页数量
     * @return array 查询结果
     */
    public function getListByBizNo(string $bizNo, array $conditions = [], int $page = 1, int $pageSize = 20): array
    {
        $query = OperationLog::query()->where('biz_no', $bizNo);
        
        // 应用查询条件
        $this->applyConditions($query, $conditions);
        
        // 排序
        $query->orderBy('created_at', 'desc');
        
        // 分页查询
        $total = $query->count();
        $list = $query->forPage($page, $pageSize)->get();
        
        return [
            'total' => $total,
            'list' => $list,
            'page' => $page,
            'pageSize' => $pageSize,
        ];
    }
    
    /**
     * 应用查询条件
     * 
     * @param Builder $query 查询构造器
     * @param array $conditions 查询条件
     */
    protected function applyConditions(Builder $query, array $conditions): void
    {
        // 按操作类别查询
        if (!empty($conditions['category'])) {
            $query->where('category', $conditions['category']);
        }
        
        // 按操作动作查询
        if (!empty($conditions['action'])) {
            $query->where('action', $conditions['action']);
        }
        
        // 按操作人查询
        if (!empty($conditions['user_id'])) {
            $query->where('user_id', $conditions['user_id']);
        }
        
        // 按操作人姓名模糊查询
        if (!empty($conditions['user_name'])) {
            $query->where('user_name', 'like', "%{$conditions['user_name']}%");
        }
        
        // 按组织查询
        if (!empty($conditions['organization_code'])) {
            $query->where('organization_code', $conditions['organization_code']);
        }
        
        // 按内容模糊查询
        if (!empty($conditions['content'])) {
            $query->where('content', 'like', "%{$conditions['content']}%");
        }
        
        // 按创建时间范围查询
        if (!empty($conditions['start_time'])) {
            $query->where('created_at', '>=', $conditions['start_time']);
        }
        
        if (!empty($conditions['end_time'])) {
            $query->where('created_at', '<=', $conditions['end_time']);
        }
    }
} 