<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledgebase_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('knowledgebase_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->boolean('is_visible')->default(true);
            $table->boolean('hidden_from_guests')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('parent_id');
            $table->index('is_visible');
            $table->index('sort_order');
            $table->index(['is_visible', 'sort_order']);
        });

        Schema::create('knowledgebase_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained('knowledgebase_categories')->restrictOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('excerpt', 500)->nullable();
            $table->longText('content');
            $table->json('tags')->nullable();
            $table->boolean('is_published')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('hidden_from_guests')->default(false);
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('unhelpful_count')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index('category_id');
            $table->index('is_published');
            $table->index('is_featured');
            $table->index('views_count');
            $table->index('published_at');
            $table->index('sort_order');
            $table->index(['is_published', 'is_featured']);
            $table->index(['category_id', 'sort_order']);
        });

        Schema::create('knowledgebase_article_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledgebase_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_hash', 64);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['article_id', 'visitor_hash', 'created_at']);
            $table->index(['article_id', 'created_at']);
        });

        Schema::create('knowledgebase_article_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('knowledgebase_articles')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visitor_hash', 64);
            $table->boolean('is_helpful');
            $table->timestamps();

            $table->unique(['article_id', 'visitor_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledgebase_article_votes');
        Schema::dropIfExists('knowledgebase_article_views');
        Schema::dropIfExists('knowledgebase_articles');
        Schema::dropIfExists('knowledgebase_categories');
    }
};
