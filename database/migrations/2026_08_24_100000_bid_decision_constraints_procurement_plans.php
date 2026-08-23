<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Semua additive & backward-compatible: keputusan bid, log kendala, rencana pengadaan. */
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $t) {
            if (! Schema::hasColumn('tenders', 'bid_decision_json')) {
                $t->json('bid_decision_json')->nullable()->after('status');
                $t->timestamp('bid_decision_at')->nullable()->after('bid_decision_json');
                $t->foreignId('bid_decision_by')->nullable()->after('bid_decision_at')->constrained('users')->nullOnDelete();
            }
        });

        Schema::create('constraint_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('bored_pile_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 30)->index(); // drawing|material|equipment|manpower|permit|client|weather|subcontractor|technical
            $t->string('title', 150);
            $t->text('description');
            $t->text('impact_notes')->nullable();
            $t->string('status', 20)->default('open')->index(); // open|in_progress|resolved
            $t->date('raised_at');
            $t->date('target_date')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->text('resolution_notes')->nullable();
            $t->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['project_id', 'status']);
        });

        Schema::create('procurement_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_wbs_id')->nullable()->constrained('project_wbs')->nullOnDelete();
            $t->foreignId('item_id')->nullable()->constrained()->nullOnDelete();
            $t->string('title', 180);
            $t->decimal('quantity', 20, 4);
            $t->decimal('estimated_value', 20, 2)->default(0);
            $t->date('required_date')->index();
            $t->date('planned_pr_date')->nullable();
            $t->date('planned_po_date')->nullable();
            $t->foreignId('vendor_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('purchase_request_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $t->string('status', 20)->default('planned')->index(); // planned|pr_created|po_created|received|cancelled
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_plans');
        Schema::dropIfExists('constraint_logs');
        Schema::table('tenders', function (Blueprint $t) {
            foreach (['bid_decision_json', 'bid_decision_at'] as $col) {
                if (Schema::hasColumn('tenders', $col)) {
                    $t->dropColumn($col);
                }
            }
            if (Schema::hasColumn('tenders', 'bid_decision_by')) {
                $t->dropConstrainedForeignId('bid_decision_by');
            }
        });
    }
};
