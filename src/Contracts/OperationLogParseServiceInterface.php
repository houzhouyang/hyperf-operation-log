<?php

declare(strict_types=1);

namespace HyperfOperationLog\Contracts;

interface OperationLogParseServiceInterface
{
    /**
     * 添加模板到解析队列
     * 
     * @param string $template 模板字符串
     */
    public function add(string $template): void;

    /**
     * 解析队列中的所有模板内容
     * 
     * @return array 解析后的内容数组
     */
    public function parse(): array;

    /**
     * 解析模板字符串中的占位符
     * 
     * @param string $template 模板字符串
     * @return string 解析后的字符串
     */
    public function parseTemplate(string $template): string;
} 