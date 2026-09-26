<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrated_marketplace_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('marketplace_resource_id')->nullable();
            $table->string('resource_slug')->unique();
            $table->string('resource_name');
            $table->string('category')->nullable();
            $table->unsignedBigInteger('version_id')->nullable();
            $table->string('version')->nullable();
            $table->string('namespace')->nullable();
            $table->string('identifier')->nullable();
            $table->string('path');
            $table->timestamp('installed_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integrated_marketplace_installations');
    }
};
