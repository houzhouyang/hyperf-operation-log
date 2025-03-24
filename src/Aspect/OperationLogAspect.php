<?php

declare(strict_types=1);

namespace HyperfOperationLog\Aspect;

use HyperfOperationLog\Annotation\OperationLog;
use HyperfOperationLog\Contracts\UserProviderInterface;
use HyperfOperationLog\Events\OperationLogCreatedEvent;
use HyperfOperationLog\OperationLogContext;
use HyperfOperationLog\Services\OperationLogParseService;
use Hyperf\Di\Annotation\Aspect;
use Hyperf\Di\Aop\AroundInterface;
use Hyperf\Di\Aop\ProceedingJoinPoint;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Hyperf\Contract\ConfigInterface;
use Symfony\Component\Console\Helper\Dumper;

#[Aspect]
class OperationLogAspect implements AroundInterface
{
    /**
     * 要切入的类，可以多个，亦可通过 :: 表示到方法级别
     * 
     * @var array
     */
    public array $classes = [];

    /**
     * 要切入的注解，具体切入的还是使用了这些注解的类，仅可切入类注解和类方法注解
     * 
     * @var array
     */
    public array $annotations = [
        OperationLog::class,
    ];

    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    /**
     * @var EventDispatcherInterface
     */
    private EventDispatcherInterface $dispatcher;
    
    /**
     * @var UserProviderInterface
     */
    private UserProviderInterface $userProvider;
    
    /**
     * @var ConfigInterface
     */
    private ConfigInterface $config;

    public function __construct(
        ContainerInterface $container, 
        EventDispatcherInterface $dispatcher,
        UserProviderInterface $userProvider,
        ConfigInterface $config
    ) {
        $this->container = $container;
        $this->dispatcher = $dispatcher;
        $this->userProvider = $userProvider;
        $this->config = $config;
    }

    /**
     * 切面处理
     */
    public function process(ProceedingJoinPoint $proceedingJoinPoint)
    {
        // 获取请求实例
        $request = $this->container->get(RequestInterface::class);
        
        // 获取当前登录用户信息
        $authUser = $this->userProvider->getCurrentUser();
        // 获取注解信息
        $annotationMetadata = $proceedingJoinPoint->getAnnotationMetadata();
        $operationLog = $annotationMetadata->method[OperationLog::class] ?? null;
        
        if (! $operationLog) {
            return $proceedingJoinPoint->process();
        }
        
        // 执行原方法
        $response = $proceedingJoinPoint->process();
        
        // 判断是否只记录成功操作
        $onlySuccess = (bool)$this->config->get('operation_log.recording.only_success', true);
        
        if ($onlySuccess) {
            // 获取配置的成功响应码
            $successCodes = $this->config->get('operation_log.recording.success_codes', [1000]);
            
            // 获取配置的状态码字段，默认使用 ['code']
            $codeFields = $this->config->get('operation_log.recording.code_fields', ['code']);
            
            // 判断响应是否成功
            $responseCode = null;
            $isSuccessResponse = false;
            
            // 从响应中按顺序查找配置的状态码字段
            if (is_array($response)) {
                foreach ($codeFields as $field) {
                    if (isset($response[$field])) {
                        $responseCode = $response[$field];
                        // 检查响应码是否在成功码列表中
                        $isSuccessResponse = in_array($responseCode, $successCodes, true);
                        break;
                    }
                }
            }
            
            // 如果需要只记录成功操作，且当前操作未成功，则直接返回响应
            if (!$isSuccessResponse) {
                return $response;
            }
        }
        
        // 提取响应数据
        $responseData = $response['data'] ?? [];
        if (is_object($responseData)) {
            $responseData = json_decode(json_encode($responseData), true);
        }
        
        // 构建上下文数据
        $contextData = [
            'authUser' => $authUser,
            'param' => $proceedingJoinPoint->getArguments(),
            'request' => $this->getRequestData($request),
            'response' => $responseData,
            'logContext' => OperationLogContext::getLogContextData(),
        ];
        
        // 解析模板内容
        $operationLogParseService = new OperationLogParseService($contextData, $this->config);
        $operationLogParseService->add($operationLog->getContent());
        $operationLogParseService->add($operationLog->getBizNo());
        [$parseContent, $bizNo] = $operationLogParseService->parse();
        
        // 获取额外参数
        $extraParams = OperationLogContext::getLogContextData() ?? [];
        
        // 获取请求数据
        $requestData = [
            'body' => $this->getRequestData($request),
            'header' => $request->getHeaders(),
            'url' => $request->getRequestUri(),
            'method' => $request->getMethod(),
        ];
        
        // 获取当前类名和方法名作为默认值
        $className = $proceedingJoinPoint->className;
        $methodName = $proceedingJoinPoint->methodName;
        
        // 如果category为空，则使用类名，如果action为空，则使用方法名
        $category = $operationLog->getCategory() ?: $className;
        $action = $operationLog->getAction() ?: $methodName;
        
        // 准备基础日志数据
        $logData = [
            'parseContent' => $parseContent,
            'category' => $category,
            'action' => $action,
            'userName' => $authUser['userName'] ?? '未知用户',
            'userId' => $authUser['uid'] ?? '0',
            'requestData' => $requestData,
            'response' => $response,
            'extraParams' => $extraParams,
            'organizationCode' => $authUser['organizationCode'] ?? null,
        ];
        
        // 解析业务编号
        $decodedBizNo = json_decode($bizNo, true);
        $bizNoList = is_array($decodedBizNo) ? $decodedBizNo : [$bizNo];
        // 为每个业务编号触发事件
        foreach ($bizNoList as $id) {
            $this->dispatcher->dispatch(
                new OperationLogCreatedEvent(
                    $id, 
                    $logData['parseContent'],
                    $logData['category'],
                    $logData['action'],
                    $logData['userName'],
                    $logData['userId'],
                    $logData['requestData'],
                    $logData['response'],
                    $logData['extraParams'],
                    $logData['organizationCode']
                )
            );
        }
        
        return $response;
    }

    /**
     * 获取请求数据
     */
    protected function getRequestData(RequestInterface $request): array
    {
        $data = [];
        
        // 获取POST/PUT数据
        $contentType = $request->getHeaderLine('Content-Type');
        if (stripos($contentType, 'application/json') !== false) {
            $data = json_decode($request->getBody()->getContents(), true) ?: [];
        } else {
            $data = $request->getParsedBody() ?: [];
        }
        
        // 获取URL参数
        $queryParams = $request->getQueryParams();
        if (!empty($queryParams)) {
            $data = array_merge($data, $queryParams);
        }
        
        return $data;
    }
} 