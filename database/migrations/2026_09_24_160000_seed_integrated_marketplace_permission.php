<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected array $permissions = [
        'admin.integrated-marketplace' => 'Browse the integrated marketplace',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission => $description) {
            DB::table('permissions')->updateOrInsert(
                ['permission' => $permission],
                ['description' => $description]
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('permission', array_keys($this->permissions))
            ->delete();
    }
};
