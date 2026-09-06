<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('download_folders', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('is_visible');
            $table->index('sort_order');
        });

        Schema::create('download_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->constrained('download_folders')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('version')->nullable();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->boolean('is_published')->default(true);
            $table->boolean('allow_guests')->default(false);
            $table->boolean('require_any_order')->default(false);
            $table->boolean('require_active_order')->default(true);
            $table->boolean('hidden_until_eligible')->default(false);
            $table->json('package_ids')->nullable();
            $table->unsignedInteger('download_limit')->nullable();
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('download_count')->default(0);
            $table->timestamps();

            $table->index('folder_id');
            $table->index('is_published');
            $table->index('sort_order');
            $table->index(['folder_id', 'sort_order']);
        });

        Schema::create('download_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')->constrained('download_files')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['file_id', 'user_id']);
            $table->index(['file_id', 'ip_address']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_logs');
        Schema::dropIfExists('download_files');
        Schema::dropIfExists('download_folders');
    }
};
