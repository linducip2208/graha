<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('concrete_deliveries', function (Blueprint $table) {
            $table->string('payload_fingerprint', 64)->nullable()->after('idempotency_key');
            $table->index(['company_id', 'payload_fingerprint']);
        });

        DB::table('concrete_deliveries')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $payload = [
                    'project_id' => (int) $row->project_id,
                    'bored_pile_id' => (int) $row->bored_pile_id,
                    'delivery_order_number' => (string) $row->delivery_order_number,
                    'truck_number' => (string) $row->truck_number,
                    'driver_name' => (string) ($row->driver_name ?? ''),
                    'batching_plant' => (string) ($row->batching_plant ?? ''),
                    'purchase_order_id' => (string) ($row->purchase_order_id ?? ''),
                    'batch_time' => (string) ($row->batch_time ?? ''),
                    'arrived_at' => (string) ($row->arrived_at ?? ''),
                    'pour_started_at' => (string) ($row->pour_started_at ?? ''),
                    'pour_finished_at' => (string) ($row->pour_finished_at ?? ''),
                    'grade' => (string) ($row->grade ?? ''),
                    'ordered_volume_m3' => (string) $row->ordered_volume_m3,
                    'delivered_volume_m3' => (string) $row->delivered_volume_m3,
                    'accepted_volume_m3' => (string) $row->accepted_volume_m3,
                    'rejected_volume_m3' => (string) $row->rejected_volume_m3,
                    'slump_cm' => (string) ($row->slump_cm ?? ''),
                    'sample_number' => (string) ($row->sample_number ?? ''),
                ];
                ksort($payload);
                DB::table('concrete_deliveries')->where('id', $row->id)->update([
                    'payload_fingerprint' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('concrete_deliveries', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'payload_fingerprint']);
            $table->dropColumn('payload_fingerprint');
        });
    }
};
