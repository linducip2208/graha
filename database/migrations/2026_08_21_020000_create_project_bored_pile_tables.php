<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $t) {
            $t->decimal('overbreak_tolerance_percent', 7, 3)->default(15)->after('estimated_cost');
            $t->date('planned_start')->nullable();
            $t->date('planned_end')->nullable();
            $t->timestamp('closed_at')->nullable();
        });
        Schema::create('project_zones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->string('code', 30);
            $t->string('name');
            $t->timestamps();
            $t->unique(['project_id', 'code']);
        });
        Schema::create('project_wbs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('project_wbs')->restrictOnDelete();
            $t->string('code', 50);
            $t->string('name');
            $t->decimal('budget', 20, 2)->default(0);
            $t->timestamps();
            $t->unique(['project_id', 'code']);
        });
        Schema::create('project_cost_codes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->string('code', 50);
            $t->string('name');
            $t->string('category', 30);
            $t->timestamps();
            $t->unique(['project_id', 'code']);
        });
        Schema::create('bored_piles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_zone_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_wbs_id')->nullable()->constrained('project_wbs')->restrictOnDelete();
            $t->foreignId('project_cost_code_id')->nullable()->constrained('project_cost_codes')->restrictOnDelete();
            $t->string('pile_number', 60);
            $t->decimal('coordinate_x', 14, 4)->nullable();
            $t->decimal('coordinate_y', 14, 4)->nullable();
            $t->decimal('diameter_mm', 10, 2);
            $t->decimal('planned_depth_m', 10, 3);
            $t->decimal('actual_depth_m', 10, 3)->nullable();
            $t->decimal('theoretical_concrete_m3', 14, 4)->nullable();
            $t->decimal('actual_concrete_m3', 14, 4)->nullable();
            $t->decimal('overbreak_percent', 9, 3)->nullable();
            $t->boolean('overbreak_exceeded')->default(false)->index();
            $t->string('status', 30)->default('planned')->index();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->unique(['project_id', 'pile_number']);
            $t->index(['project_id', 'project_zone_id', 'status']);
        });
        Schema::create('bored_pile_activities', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->string('from_status', 30);
            $t->string('to_status', 30);
            $t->timestamp('started_at');
            $t->timestamp('finished_at')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
        Schema::create('project_daily_reports', function (Blueprint $t) {
            $t->id();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->date('report_date');
            $t->string('weather', 60)->nullable();
            $t->unsignedInteger('manpower_count')->default(0);
            $t->text('work_summary');
            $t->text('issues')->nullable();
            $t->foreignId('prepared_by')->constrained('users')->restrictOnDelete();
            $t->string('status', 30)->default('draft');
            $t->timestamps();
            $t->unique(['project_id', 'report_date']);
        });
        Schema::create('bored_pile_inspections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('bored_pile_id')->constrained()->restrictOnDelete();
            $t->string('inspection_type', 40);
            $t->string('result', 20);
            $t->json('measurements')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('inspector_id')->constrained('users')->restrictOnDelete();
            $t->timestamp('inspected_at');
            $t->timestamps();
            $t->index(['bored_pile_id', 'inspection_type', 'result'], 'pile_inspection_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bored_pile_inspections');
        Schema::dropIfExists('project_daily_reports');
        Schema::dropIfExists('bored_pile_activities');
        Schema::dropIfExists('bored_piles');
        Schema::dropIfExists('project_cost_codes');
        Schema::dropIfExists('project_wbs');
        Schema::dropIfExists('project_zones');
        Schema::table('projects', fn (Blueprint $t) => $t->dropColumn(['overbreak_tolerance_percent', 'planned_start', 'planned_end', 'closed_at']));
    }
};
