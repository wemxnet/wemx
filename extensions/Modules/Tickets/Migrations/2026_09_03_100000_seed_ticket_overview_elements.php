<?php

use Extensions\Modules\Tickets\Module;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ticketsEnabled = DB::table('extensions')
            ->where('identifier', 'tickets')
            ->where('status', 'enabled')
            ->exists();

        if (! $ticketsEnabled) {
            return;
        }

        $now = now();

        foreach ((new Module)->elements() as $element) {
            if (empty($element['view'])) {
                continue;
            }

            $exists = DB::table('extension_elements')
                ->where('extension_identifier', 'tickets')
                ->where('element', $element['element'])
                ->where('view', $element['view'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('extension_elements')->insert([
                'extension_identifier' => 'tickets',
                'element' => $element['element'],
                'view' => $element['view'],
                'permission' => $element['permission'] ?? null,
                'attributes' => json_encode($element['attributes'] ?? []),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('extension_elements')
            ->where('extension_identifier', 'tickets')
            ->whereIn('view', [
                'tickets::admin_area.default.dashboard.widgets.needs-reply',
                'tickets::admin_area.default.users.widgets.user-tickets',
            ])
            ->delete();
    }
};
