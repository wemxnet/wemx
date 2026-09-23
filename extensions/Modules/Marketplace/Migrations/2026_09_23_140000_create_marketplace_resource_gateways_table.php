<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('marketplace_resource_gateways')) {
            Schema::create('marketplace_resource_gateways', function (Blueprint $table) {
                $table->id();
                $table->foreignId('resource_id')->constrained('marketplace_resources')->cascadeOnDelete();
                $table->foreignId('gateway_config_id')->constrained('marketplace_creator_gateway_configs')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['resource_id', 'gateway_config_id']);
            });
        }

        $existing = DB::table('marketplace_resource_gateways')
            ->pluck('gateway_config_id', 'resource_id')
            ->all();

        $rows = DB::table('marketplace_resources')
            ->whereNotNull('gateway_config_id')
            ->get(['id', 'gateway_config_id', 'created_at', 'updated_at']);

        foreach ($rows as $row) {
            if (isset($existing[$row->id]) && (int) $existing[$row->id] === (int) $row->gateway_config_id) {
                continue;
            }

            $alreadyLinked = DB::table('marketplace_resource_gateways')
                ->where('resource_id', $row->id)
                ->where('gateway_config_id', $row->gateway_config_id)
                ->exists();

            if ($alreadyLinked) {
                continue;
            }

            DB::table('marketplace_resource_gateways')->insert([
                'resource_id' => $row->id,
                'gateway_config_id' => $row->gateway_config_id,
                'created_at' => $row->created_at ?? now(),
                'updated_at' => $row->updated_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_resource_gateways');
    }
};
