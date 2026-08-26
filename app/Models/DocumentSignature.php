<?php

namespace App\Models;

use App\Services\Storage\CompanyStorageManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentSignature extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['signed_at' => 'datetime'];
    }

    /**
     * Token verifikasi publik deterministik (ADR-075): id + HMAC(app key)
     * sehingga halaman /verify/{token} tidak bisa ditebak namun tetap
     * dapat dirender ulang dari data tanpa kolom tambahan.
     */
    public function verificationToken(): string
    {
        $mac = substr(hash_hmac('sha256', 'signature-verify:'.$this->id.':'.$this->signed_hash, (string) config('app.key')), 0, 24);

        return $this->id.'-'.$mac;
    }

    public static function findByVerificationToken(string $token): ?self
    {
        if (! preg_match('/^(\d+)-([a-f0-9]{24})$/', $token, $m)) {
            return null;
        }
        $signature = self::find((int) $m[1]);
        if ($signature === null) {
            return null;
        }
        $expected = substr(hash_hmac('sha256', 'signature-verify:'.$signature->id.':'.$signature->signed_hash, (string) config('app.key')), 0, 24);

        return hash_equals($expected, $m[2]) ? $signature : null;
    }

    /** Validitas kriptografis: file utuh, hash cocok dengan versi & signature. */
    public function verificationResult(): array
    {
        $version = $this->version()->with('document')->first();
        $checks = [
            'version_found' => $version !== null,
            'status_completed' => $this->status === 'completed',
            'hash_bound' => false,
            'file_intact' => false,
        ];
        if ($version !== null && hash_equals((string) $this->signed_hash, (string) $version->sha256)) {
            $checks['hash_bound'] = true;
            try {
                $disk = app(CompanyStorageManager::class)->forDocumentVersion($version);
                $checks['file_intact'] = $disk->exists($version->path)
                    && hash_equals((string) $version->sha256, hash('sha256', (string) $disk->get($version->path)));
            } catch (\Throwable) {
                $checks['file_intact'] = false;
            }
        }
        $valid = ! in_array(false, $checks, true);

        return ['valid' => $valid, 'checks' => $checks, 'version' => $version];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(SignatureProvider::class, 'signature_provider_id');
    }
}
