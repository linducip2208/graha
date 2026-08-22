<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel_tanks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('warehouse_id')->nullable()->constrained()->nullOnDelete();
            $t->string('code', 40);
            $t->string('name');
            $t->decimal('capacity_l', 12, 2);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
        });
        Schema::create('fuel_tank_transactions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('fuel_tank_id')->constrained()->cascadeOnDelete();
            $t->string('type', 20)->index();
            $t->timestamp('occurred_at');
            $t->foreignId('equipment_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('project_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('reference', 120)->nullable();
            $t->decimal('liters', 12, 2);
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->string('idempotency_key', 120);
            $t->timestamps();
            $t->unique(['fuel_tank_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_tank_transactions');
        Schema::dropIfExists('fuel_tanks');
    }
};
