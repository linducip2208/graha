<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Backlog F/G gelombang 7: korespondensi kontrak (ADR-071). Engine PPh 21 murni service tanpa skema baru. */
    public function up(): void
    {
        Schema::create('contract_correspondences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('project_award_id')->constrained()->restrictOnDelete();
            $t->string('direction', 10); // in|out
            $t->string('ref_number', 120);
            $t->date('correspondence_date');
            $t->string('subject');
            $t->text('body')->nullable();
            $t->foreignId('document_version_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_correspondences');
    }
};
