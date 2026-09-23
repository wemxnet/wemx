<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketplace_resources', function (Blueprint $table) {
            $table->unsignedInteger('reviews_count')->default(0)->after('purchases_count');
            $table->decimal('reviews_avg', 3, 2)->default(0)->after('reviews_count');
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

        Schema::table('marketplace_resources', function (Blueprint $table) {
            $table->dropColumn(['reviews_count', 'reviews_avg']);
        });
    }
};
