<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_resources')) {
            return;
        }

        if (Schema::hasColumn('marketplace_resources', 'version_limit')) {
            return;
        }

        Schema::table('marketplace_resources', function (Blueprint $table) {
            $table->unsignedInteger('version_limit')->nullable()->after('purchases_count');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_resources')) {
            return;
        }

        if (! Schema::hasColumn('marketplace_resources', 'version_limit')) {
            return;
        }

        Schema::table('marketplace_resources', function (Blueprint $table) {
            $table->dropColumn('version_limit');
        });
    }
};
