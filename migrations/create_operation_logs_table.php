<?php

declare(strict_types=1);

use Hyperf\Database\Schema\Schema;
use Hyperf\Database\Schema\Blueprint;
use Hyperf\Database\Migrations\Migration;

class CreateOperationLogsTable extends Migration
{
    /**
     * 运行数据库迁移
     */
    public function up(): void
    {
        Schema::create('operation_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('biz_no', 64)->comment('业务编号');
            $table->string('content')->comment('操作内容');
            $table->string('category', 64)->comment('操作类别');
            $table->string('action', 64)->comment('操作动作');
            $table->string('user_name', 64)->comment('操作人姓名');
            $table->string('user_id', 64)->comment('操作人ID');
            $table->string('organization_code', 64)->nullable()->comment('组织编码');
            $table->json('request_data')->nullable()->comment('请求数据');
            $table->json('response_data')->nullable()->comment('响应数据');
            $table->json('extra_params')->nullable()->comment('额外参数');
            $table->ipAddress('ip')->nullable()->comment('IP地址');
            $table->string('user_agent', 512)->nullable()->comment('用户代理');
            $table->timestamp('created_at')->comment('创建时间');
            $table->timestamp('updated_at')->nullable()->comment('更新时间');
            
            $table->index('biz_no');
            $table->index('user_id');
            $table->index('organization_code');
            $table->index(['category', 'action']);
            $table->index('created_at');
        });
    }

    /**
     * 回滚数据库迁移
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_logs');
    }
} 