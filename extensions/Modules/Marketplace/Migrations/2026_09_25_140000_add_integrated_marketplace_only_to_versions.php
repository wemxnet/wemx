<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_resource_versions')) {
            return;
        }

        if (Schema::hasColumn('marketplace_resource_versions', 'integrated_marketplace_only')) {
            return;
        }

        Schema::table('marketplace_resource_versions', function (Blueprint $table) {
            $table->boolean('integrated_marketplace_only')->default(false)->after('available_on_integrated_marketplace');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('marketplace_resource_versions')) {
            return;
        }

        if (! Schema::hasColumn('marketplace_resource_versions', 'integrated_marketplace_only')) {
            return;
        }

        Schema::table('marketplace_resource_versions', function (Blueprint $table) {
            $table->dropColumn('integrated_marketplace_only');
        });
    }
};
