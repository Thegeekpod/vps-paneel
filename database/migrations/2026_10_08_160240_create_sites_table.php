<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->unique();
            $table->string('aliases')->nullable();
            $table->string('type'); // 'nextjs', 'laravel', 'wordpress', 'php'
            $table->string('status')->default('running'); // 'running', 'stopped', 'deploying', 'error'
            $table->string('php_version')->default('8.3');
            $table->string('node_version')->nullable()->default('22');
            $table->integer('port')->nullable(); // for Next.js internal port (e.g. 3000)
            $table->string('root_path');
            $table->string('web_directory')->default('/public');
            $table->boolean('ssl_enabled')->default(false);
            $table->boolean('ssl_auto_renew')->default(true);
            $table->timestamp('ssl_issued_at')->nullable();
            $table->timestamp('ssl_expires_at')->nullable();
            $table->string('git_repository')->nullable();
            $table->string('git_branch')->nullable()->default('main');
            $table->boolean('auto_deploy')->default(false);
            $table->text('deploy_script')->nullable();
            $table->text('env_content')->nullable();
            $table->text('custom_nginx_config')->nullable();
            $table->unsignedBigInteger('database_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sites');
    }
};
