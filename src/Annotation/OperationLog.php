<?php

declare(strict_types=1);

namespace HyperfOperationLog\Annotation;

use Attribute;
use Hyperf\Di\Annotation\AbstractAnnotation;

#[Attribute(Attribute::TARGET_METHOD)]
class OperationLog extends AbstractAnnotation
{
    /**
     * @param string $content 操作内容描述，支持模板语法
     * @param string $bizNo 业务编号，支持模板语法
     * @param string $category 操作日志类别，默认为注解所在类名
     * @param string $action 操作动作，默认为注解所在方法名
     */
    public function __construct(
        public string $content,
        public string $bizNo,
        public string $category = '',
        public string $action = ''
    ) {
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getBizNo(): string
    {
        return $this->bizNo;
    }
} 