<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->string('default_extract_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->index(['is_visible', 'sort_order']);
        });

        Schema::create('marketplace_creator_gateway_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('driver');
            $table->text('credentials');
            $table->json('settings')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'driver']);
            $table->index(['user_id', 'is_enabled']);
        });

        Schema::create('marketplace_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('marketplace_categories')->restrictOnDelete();
            $table->foreignId('gateway_config_id')->nullable()->constrained('marketplace_creator_gateway_configs')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description');
            $table->longText('description');
            $table->string('icon_disk')->nullable();
            $table->string('icon_path')->nullable();
            $table->string('website_url')->nullable();
            $table->string('docs_url')->nullable();
            $table->string('source_url')->nullable();
            $table->string('support_url')->nullable();
            $table->decimal('price', 16, 8)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->string('license_type')->default('proprietary');
            $table->json('tags')->nullable();
            $table->boolean('available_on_integrated_marketplace')->default(true);
            $table->string('status')->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_official')->default(false);
            $table->boolean('is_disabled')->default(false);
            $table->timestamp('featured_until')->nullable();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('downloads_count')->default(0);
            $table->unsignedInteger('purchases_count')->default(0);
            $table->unsignedInteger('version_limit')->nullable();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->decimal('reviews_avg', 3, 2)->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'is_featured']);
            $table->index(['status', 'is_disabled']);
            $table->index(['category_id', 'status']);
            $table->index(['available_on_integrated_marketplace', 'status']);
            $table->index(['views_count', 'downloads_count']);
        });

        Schema::create('marketplace_resource_gateways', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('marketplace_resources')->cascadeOnDelete();
            $table->foreignId('gateway_config_id')->constrained('marketplace_creator_gateway_configs')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['resource_id', 'gateway_config_id']);
        });

        Schema::create('marketplace_resource_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('marketplace_resources')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('version');
            $table->string('wemx_version')->default('*');
            $table->text('changelog')->nullable();
            $table->boolean('available_on_integrated_marketplace')->default(true);
            $table->boolean('integrated_marketplace_only')->default(false);
            $table->string('extract_path')->nullable();
            $table->string('rename_extract_to')->nullable();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->string('status')->default('pending');
            $table->boolean('notify_customers')->default(false);
            $table->unsignedInteger('downloads_count')->default(0);
            $table->timestamps();

            $table->unique(['resource_id', 'version']);
            $table->index(['resource_id', 'status', 'created_at']);
            $table->index(['available_on_integrated_marketplace', 'status']);
        });

        Schema::create('marketplace_resource_team_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('marketplace_resources')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('developer');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['resource_id', 'user_id']);
        });

        Schema::create('marketplace_sales', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('resource_id')->constrained('marketplace_resources')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('marketplace_resource_versions')->nullOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gateway_config_id')->nullable()->constrained('marketplace_creator_gateway_configs')->nullOnDelete();
            $table->string('driver')->nullable();
            $table->decimal('amount', 16, 8);
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending');
            $table->string('gateway_reference')->nullable();
            $table->text('gateway_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index(['seller_id', 'status']);
            $table->index(['resource_id', 'status']);
            $table->index('gateway_reference');
        });

        Schema::create('marketplace_licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('marketplace_resources')->cascadeOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained('marketplace_sales')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('license_key')->unique();
            $table->string('domain')->nullable();
            $table->string('status')->default('active');
            $table->string('source')->default('purchase');
            $table->string('payment_method')->nullable();
            $table->string('transaction_id')->nullable();
            $table->timestamp('purchased_at')->nullable();
            $table->unsignedInteger('max_activations')->default(1);
            $table->unsignedInteger('activations_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamps();

            $table->unique(['resource_id', 'user_id']);
            $table->index(['resource_id', 'status']);
            $table->index(['resource_id', 'purchased_at']);
        });

        Schema::create('marketplace_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('marketplace_resources')->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('marketplace_resource_versions')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('license_id')->nullable()->constrained('marketplace_licenses')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('source')->default('marketplace');
            $table->timestamps();

            $table->index(['resource_id', 'created_at']);
            $table->index(['version_id', 'created_at']);
        });

        Schema::create('marketplace_resource_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('marketplace_resources')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_hash', 64);
            $table->timestamps();

            $table->index(['resource_id', 'visitor_hash', 'created_at']);
        });

        Schema::create('marketplace_resource_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('marketplace_resources')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['resource_id', 'user_id']);
            $table->index(['resource_id', 'is_visible', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_resource_reviews');
        Schema::dropIfExists('marketplace_resource_views');
        Schema::dropIfExists('marketplace_downloads');
        Schema::dropIfExists('marketplace_licenses');
        Schema::dropIfExists('marketplace_sales');
        Schema::dropIfExists('marketplace_resource_team_members');
        Schema::dropIfExists('marketplace_resource_versions');
        Schema::dropIfExists('marketplace_resource_gateways');
        Schema::dropIfExists('marketplace_resources');
        Schema::dropIfExists('marketplace_creator_gateway_configs');
        Schema::dropIfExists('marketplace_categories');
    }
};
