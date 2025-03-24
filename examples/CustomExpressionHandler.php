<?php

namespace Examples;

use HyperfOperationLog\Contracts\OperationLogExpressionHandlerInterface;

/**
 * 示例自定义表达式处理器：计算数学表达式
 */
class MathExpressionHandler implements OperationLogExpressionHandlerInterface
{
    /**
     * 处理表达式
     * 
     * @param string $expression 表达式
     * @param array $context 上下文数据
     * @param object $service 服务对象
     * @return string|null 处理结果，如果无法处理则返回null
     */
    public function handle(string $expression, array $context, object $service): ?string
    {
        // 匹配 (math)1+2*3 格式的表达式
        if (preg_match('/^\(math\)(.+)$/', $expression, $matches)) {
            $mathExpression = $matches[1];
            
            // 安全地计算表达式，替换变量
            $processedExpression = preg_replace_callback(
                '/\$(\w+)/',
                function($varMatches) use ($context) {
                    $varName = $varMatches[1];
                    if (isset($context[$varName]) && is_numeric($context[$varName])) {
                        return (float)$context[$varName];
                    }
                    return 0;
                },
                $mathExpression
            );
            
            // 只允许基本运算符和数字
            if (preg_match('/^[0-9\+\-\*\/\.\(\) ]+$/', $processedExpression)) {
                try {
                    // 使用eval安全地计算表达式
                    $result = 0;
                    eval('$result = ' . $processedExpression . ';');
                    return (string)$result;
                } catch (\Throwable $e) {
                    // 计算失败
                    return "数学表达式错误";
                }
            }
        }
        
        return null;
    }
    
    /**
     * 获取处理器名称
     *
     * @return string
     */
    public function getName(): string
    {
        return 'math_expression';
    }
    
    /**
     * 获取处理器描述
     *
     * @return string
     */
    public function getDescription(): string
    {
        return '计算数学表达式，格式为：{(math)1+2*3} 或 {(math)$price*$quantity}';
    }
}

/**
 * 使用示例：
 * 
 * 1. 在项目的配置文件中添加处理器
 * 
 * // operation_log.php 配置文件中
 * 'expression_handlers' => [
 *     'custom' => [
 *         // 添加数学表达式处理器
 *         \Examples\MathExpressionHandler::class,
 *     ],
 * ],
 * 
 * 2. 在模板中使用
 * 
 * // 直接计算
 * $service->add("总金额: {(math)100*1.2} 元");
 * // 结果: "总金额: 120 元"
 * 
 * // 使用变量
 * $context = [
 *     'price' => 50,
 *     'quantity' => 3,
 * ];
 * $service = new OperationLogParseService($context, $config);
 * $service->add("总金额: {(math)$price*$quantity} 元");
 * // 结果: "总金额: 150 元"
 */ 