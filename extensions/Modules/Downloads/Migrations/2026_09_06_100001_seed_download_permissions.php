<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    protected array $permissions = [
        'admin.downloads' => 'View the downloads library',
        'admin.downloads.create' => 'Create download folders and upload files',
        'admin.downloads.update' => 'Update download folders and files',
        'admin.downloads.delete' => 'Delete download folders and files',
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
