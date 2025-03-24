<?php

declare(strict_types=1);

namespace HyperfOperationLog\Contracts;

interface UserProviderInterface
{
    /**
     * 获取当前操作用户信息
     * 
     * @return array 包含用户信息的数组，至少包含 uid, realName, organizationCode 等字段
     */
    public function getCurrentUser(): array;
} 