<?php

namespace Database\Seeders;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Demo document control (ADR-079): registry dokumen multi-versi
 * (draft/approved/superseded/signed), transmittal ke konsultan, dan tanda
 * tangan internal. Binary PDF placeholder lokal bertanda DEMO/SAMPLE.
 */
class DemoDocumentSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = DemoDataSeeder::company()->id;
        $documentController = DemoDataSeeder::user('document.controller@grahapondasi.test');
        $director = DemoDataSeeder::user('direktur@grahapondasi.test');

        $specs = [
            ['DOC-DEMO-001', 'shop_drawing', 'Shop Drawing Bored Pile Zona A', 'approved', 'unsigned', 2],
            ['DOC-DEMO-002', 'method_statement', 'Metode Pelaksanaan Tremie Concreting', 'approved', 'signed', 1],
            ['DOC-DEMO-003', 'quality_plan', 'Rencana Mutu Proyek Karawang', 'reviewed', 'unsigned', 3],
            ['DOC-DEMO-004', 'daily_report_template', 'Format Laporan Harian Site (revisi)', 'draft', 'unsigned', 1],
        ];
        foreach ($specs as [$number, $type, $title, $workflowStatus, $signatureStatus, $versionCount]) {
            $document = Document::firstOrCreate(
                ['company_id' => $companyId, 'number' => $number],
                [
                    'document_type' => $type, 'title' => $title, 'owner_id' => $documentController->id,
                    'project_id' => Project::where('company_id', $companyId)->where('code', 'PRJ-2601')->value('id'),
                    'workflow_status' => 'draft',
                ]
            );

            for ($v = 1; $v <= $versionCount; $v++) {
                if (DocumentVersion::where('document_id', $document->id)->where('version', $v)->exists()) {
                    continue;
                }
                $pdfContent = $this->demoPdf("{$number} v{$v} — {$title}");
                $path = "demo-documents/{$number}/v{$v}.pdf";
                Storage::disk('local')->put($path, $pdfContent);

                DocumentVersion::create([
                    'document_id' => $document->id, 'version' => $v,
                    'revision' => (string) ($v - 1),
                    'change_reason' => $v === 1 ? 'Demo seed: rilis awal.' : 'Demo seed: revisi catatan konsultan.',
                    'disk' => 'local', 'path' => $path,
                    'mime_type' => 'application/pdf', 'size_bytes' => strlen($pdfContent),
                    'sha256' => hash('sha256', $pdfContent),
                    'is_signed' => $signatureStatus === 'signed' && $v === $versionCount,
                    'created_by' => $documentController->id,
                ]);
            }

            // Tanda tangan internal pada versi terakhir dokumen method statement.
            if ($signatureStatus === 'signed') {
                $latest = DocumentVersion::where('document_id', $document->id)->orderByDesc('version')->first();
                if ($latest !== null && ! Str::of($signatureStatus)->exactly('unsigned')) {
                    DB::table('document_signatures')->updateOrInsert(
                        ['document_version_id' => $latest->id, 'signer_name' => $director->name, 'signature_type' => 'internal'],
                        [
                            'company_id' => $companyId, 'signer_id' => $director->id,
                            'signer_position' => 'Direktur Operasi', 'status' => 'signed',
                            'signed_hash' => hash('sha256', "demo-signature:{$latest->id}:{$director->id}"),
                            'signed_at' => now()->subDays(18), 'created_at' => now(), 'updated_at' => now(),
                        ]
                    );
                    $document->update(['signature_status' => 'signed']);
                }
            }
            $document->update(['workflow_status' => $workflowStatus]);
        }

        // Transmittal pengiriman shop drawing ke konsultan.
        $transmittalId = DB::table('document_transmittals')->where('company_id', $companyId)->where('number', 'TRM-DEMO-001')->value('id');
        if (! $transmittalId) {
            $transmittalId = DB::table('document_transmittals')->insertGetId([
                'company_id' => $companyId, 'number' => 'TRM-DEMO-001', 'recipient' => 'PT Konsultan Pengawas Cikarang',
                'purpose' => 'Pengiriman shop drawing revisi untuk review.', 'transmit_date' => now()->subDays(15)->toDateString(),
                'method' => 'email', 'status' => 'acknowledged', 'acknowledged_at' => now()->subDays(14),
                'notes' => 'Demo seed.', 'created_by' => $documentController->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $drawingDoc = Document::where('company_id', $companyId)->where('number', 'DOC-DEMO-001')->first();
            $drawingVersion = $drawingDoc !== null ? DocumentVersion::where('document_id', $drawingDoc->id)->orderByDesc('version')->first() : null;
            if ($drawingVersion !== null) {
                DB::table('document_transmittal_items')->insert([
                    'document_transmittal_id' => $transmittalId, 'document_version_id' => $drawingVersion->id,
                    'copies' => 2, 'note' => '2 hardcopy + softcopy', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /** PDF minimal valid bertanda DEMO/SAMPLE — tanpa dependency eksternal. */
    private function demoPdf(string $title): string
    {
        $safe = str_replace(['(', ')', '\\'], '', $title.' - DEMO / SAMPLE. Dokumen sintetis untuk demonstrasi.');
        $body = "BT /F1 12 Tf 60 780 Td ({$safe}) Tj ET";

        return "%PDF-1.4\n"
            ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
            ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
            ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n"
            .'4 0 obj<</Length '.strlen($body).">>stream\n{$body}\nendstream endobj\n"
            ."5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\n"
            ."trailer<</Root 1 0 R>>\n%%EOF";
    }
}
