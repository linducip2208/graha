<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_evidences', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('evidence_type', 30);
            $t->unsignedBigInteger('evidence_id');
            $t->string('disk_path', 300);
            $t->string('original_name', 200);
            $t->string('mime', 80);
            $t->unsignedInteger('size_kb');
            $t->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $t->timestamps();
            $t->index(['evidence_type', 'evidence_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_evidences');
    }
};
