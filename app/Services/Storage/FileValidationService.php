<?php

namespace App\Services\Storage;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Validasi file berdasarkan KONTEN (magic bytes + finfo), bukan sekadar
 * ekstensi/nama file. Whitelist ketat: JPEG/PNG/WebP untuk foto, PDF untuk
 * dokumen. SVG/HTML/JS/PHP/EXE/binary tidak dikenal otomatis ditolak.
 */
class FileValidationService
{
    private const SIGNATURES = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89PNG\x0D\x0A\x1A\x0A"],
        'image/webp' => ['RIFF'],
        'application/pdf' => ['%PDF-'],
    ];

    public function validateImage(UploadedFile $file): string
    {
        return $this->validate($file, (array) config('objectstorage.allowed_image_mime', []), (int) config('objectstorage.max_size_image'), 'Foto harus JPG/PNG/WebP asli');
    }

    public function validatePdf(UploadedFile $file): string
    {
        return $this->validate($file, (array) config('objectstorage.allowed_pdf_mime', []), (int) config('objectstorage.max_size_pdf'), 'Dokumen harus PDF asli');
    }

    /** Mengembalikan MIME terverifikasi dari konten. */
    private function validate(UploadedFile $file, array $allowedMime, int $maxBytes, string $message): string
    {
        throw_unless($file->isValid(), ValidationException::withMessages(['file' => 'Berkas tidak valid.']));
        throw_if($file->getSize() > $maxBytes, ValidationException::withMessages(['file' => 'Ukuran maksimal '.round($maxBytes / 1024 / 1024).' MB.']));

        $contents = (string) file_get_contents($file->getRealPath());
        throw_if($contents === '', ValidationException::withMessages(['file' => $message.' — berkas kosong.']));

        $mime = (string) ($file->getMimeType() ?: '');
        throw_unless(in_array($mime, $allowedMime, true), ValidationException::withMessages(['file' => $message.'.']));

        // Verifikasi magic byte: nama file .jpg berisi HTML/JS/EXE akan tertangkap di sini.
        $matched = false;
        foreach (self::SIGNATURES[$mime] ?? [] as $signature) {
            if (str_starts_with($contents, $signature)) {
                $matched = true;
                break;
            }
        }
        // WebP: RIFF....WEBP — cek posisi 8.
        if ($mime === 'image/webp' && $matched) {
            $matched = substr($contents, 8, 4) === 'WEBP';
        }
        throw_unless($matched, ValidationException::withMessages(['file' => $message.' — isi berkas tidak cocok dengan tipenya.']));

        return $mime;
    }

    public function extensionFromName(string $originalName): string
    {
        return strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
    }
}
