<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reinforcement_cages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('number', 80);
            $t->string('design_ref', 120)->nullable();
            $t->decimal('diameter_mm', 10, 2);
            $t->decimal('total_length_m', 10, 3);
            $t->unsignedSmallInteger('segment_count')->default(1);
            $t->string('main_bar_spec', 80)->nullable();
            $t->string('spiral_spec', 80)->nullable();
            $t->string('stiffener_spec', 80)->nullable();
            $t->unsignedSmallInteger('coupler_count')->default(0);
            $t->decimal('theoretical_weight_kg', 12, 2)->nullable();
            $t->decimal('actual_weight_kg', 12, 2)->nullable();
            $t->string('heat_number', 80)->nullable();
            $t->string('mill_cert_number', 80)->nullable();
            $t->string('qc_status', 20)->default('draft')->index();
            $t->text('qc_notes')->nullable();
            $t->foreignId('qc_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('qc_at')->nullable();
            $t->string('storage_location', 150)->nullable();
            $t->timestamp('delivered_at')->nullable();
            $t->foreignId('bored_pile_id')->nullable()->constrained()->nullOnDelete();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['company_id', 'number']);
            $t->index(['bored_pile_id', 'qc_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reinforcement_cages');
    }
};
