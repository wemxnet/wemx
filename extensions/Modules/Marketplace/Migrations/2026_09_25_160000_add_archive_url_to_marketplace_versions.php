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

        if (Schema::hasColumn('marketplace_resource_versions', 'archive_url')) {
            return;
        }

        Schema::table('marketplace_resource_versions', function (Blueprint $table) {
            $table->string('archive_url', 500)->nullable()->after('rename_extract_to');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('marketplace_resource_versions', 'archive_url')) {
            return;
        }

        Schema::table('marketplace_resource_versions', function (Blueprint $table) {
            $table->dropColumn('archive_url');
        });
    }
};
