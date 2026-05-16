<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {

        Schema::create('mst_connection', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            // $table->foreignId('app_id')->constrained('mst_app')->cascadeOnDelete();
            $table->string('host', 150);
            $table->string('db_name', 150);
            $table->unsignedInteger('port')->default(3306);
            $table->string('username', 100);
            $table->string('password');
            $table->string('driver', 50)->default('mysql');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('mst_app', function (Blueprint $table) {
            $table->id();
            $table->string('app_code', 150)->unique();
            $table->string('api_key', 255)->unique();
            $table->foreignId('connection_id')->constrained('mst_connection')->cascadeOnDelete();
            $table->string('app_first_name', 100);
            $table->string('app_last_name', 100)->nullable();
            $table->string('app_logo')->nullable();
            $table->string('app_version', 50)->nullable();
            $table->text('app_description')->nullable();
            $table->string('app_author', 150)->nullable();
            $table->string('app_license', 100)->nullable();
            $table->string('app_favicon')->nullable();
            $table->string('app_url')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('mst_user', function (Blueprint $table) {
            $table->id();
            $table->string('username', 100)->unique();
            $table->string('email')->unique();
            $table->boolean('email_verified')->default(false);
            $table->string('password');
            $table->boolean('is_active')->default(true)->index();
            $table->rememberToken();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('mst_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamps();
        });

        Schema::create('role', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('display_name', 150);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('display_name', 150);
            $table->string('group', 100)->nullable()->index();
            $table->timestamps();
        });

        Schema::create('menu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('menu')->nullOnDelete();
            $table->string('type', 50)->default('item');
            $table->string('name', 150);
            $table->string('icon', 100)->nullable();
            $table->string('path')->nullable();
            $table->string('badge_key', 100)->nullable();
            $table->unsignedInteger('step')->nullable()->index();
            $table->unsignedInteger('sort_order')->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('mst_user')->nullOnDelete();
            $table->string('action', 150);
            $table->string('module', 150)->nullable()->index();
            $table->json('payload')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('mst_session', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('mst_user')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
            $table->timestamps();
        });



        Schema::create('mst_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('mst_user')->cascadeOnDelete();
            $table->string('nik', 50)->nullable()->unique();
            $table->string('full_name', 150);
            $table->string('avatar')->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('role')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('menu_item_app', function (Blueprint $table) {
            $table->foreignId('app_id')->constrained('mst_app')->cascadeOnDelete();
            $table->foreignId('menu_id')->constrained('menu')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['app_id', 'menu_id']);
        });

        Schema::create('menu_item_role', function (Blueprint $table) {
            $table->foreignId('menu_id')->constrained('menu')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('role')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['menu_id', 'role_id']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained('role')->cascadeOnDelete();
            $table->unsignedBigInteger('model_id');
            $table->string('model_type');
            $table->timestamps();

            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('user_has_apps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('mst_user')->cascadeOnDelete();
            $table->foreignId('app_id')->constrained('mst_app')->cascadeOnDelete();
            $table->foreignId('role_id')->nullable()->constrained('role')->nullOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['user_id', 'app_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_has_apps');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('menu_item_role');
        Schema::dropIfExists('menu_item_app');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('mst_profiles');
        Schema::dropIfExists('mst_connection');
        Schema::dropIfExists('mst_session');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('menu');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('role');
        Schema::dropIfExists('mst_password_reset_tokens');
        Schema::dropIfExists('mst_user');
        Schema::dropIfExists('mst_app');
    }
};
