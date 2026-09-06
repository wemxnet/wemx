<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected array $permissions = [
        'admin.emails.mass-mails' => 'Send mass emails to customers',
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
