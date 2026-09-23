<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $addedDisabled = false;

        Schema::table('marketplace_resources', function (Blueprint $table) use (&$addedDisabled) {
            if (! Schema::hasColumn('marketplace_resources', 'is_official')) {
                $table->boolean('is_official')->default(false)->after('is_featured');
            }

            if (! Schema::hasColumn('marketplace_resources', 'is_disabled')) {
                $table->boolean('is_disabled')->default(false)->after('is_official');
                $addedDisabled = true;
            }
        });

        if ($addedDisabled) {
            Schema::table('marketplace_resources', function (Blueprint $table) {
                $table->index(['status', 'is_disabled']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('marketplace_resources', function (Blueprint $table) {
            if (Schema::hasColumn('marketplace_resources', 'is_disabled')) {
                $table->dropIndex(['status', 'is_disabled']);
            }

            $columns = collect(['is_official', 'is_disabled'])
                ->filter(fn (string $column) => Schema::hasColumn('marketplace_resources', $column))
                ->values()
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
