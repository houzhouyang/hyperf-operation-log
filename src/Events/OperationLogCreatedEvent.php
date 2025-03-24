<?php

declare(strict_types=1);

namespace HyperfOperationLog\Events;

class OperationLogCreatedEvent
{
    /**
     * @param string $bizNo 业务编号
     * @param string $content 操作内容
     * @param string $category 操作类别
     * @param string $action 操作动作
     * @param string $userName 操作人姓名
     * @param string $userId 操作人ID
     * @param array $requestData 请求数据
     * @param array $responseData 响应数据
     * @param array $extraParams 额外参数
     * @param ?string $organizationCode 组织编码
     */
    public function __construct(
        public string $bizNo,
        public string $content,
        public string $category,
        public string $action,
        public string $userName,
        public string $userId,
        public array $requestData,
        public array $responseData,
        public array $extraParams = [],
        public ?string $organizationCode = null
    ) {
        $this->bizNo = $bizNo;
        $this->content = $content;
        $this->category = $category;
        $this->action = $action;
        $this->userName = $userName;
        $this->userId = $userId;
        $this->requestData = $requestData;
        $this->responseData = $responseData;
        $this->extraParams = $extraParams;
        $this->organizationCode = $organizationCode;
    }

    public function getBizNo(): string
    {
        return $this->bizNo;
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
    
    public function getUserName(): string
    {
        return $this->userName;
    }
    
    public function getUserId(): string
    {
        return $this->userId;
    }
    
    public function getRequestData(): array
    {
        return $this->requestData;
    }
    
    public function getResponseData(): array
    {
        return $this->responseData;
    }
    
    public function getExtraParams(): array
    {
        return $this->extraParams;
    }
    
    public function getOrganizationCode(): ?string
    {
        return $this->organizationCode;
    }
    
    
    
    

} 