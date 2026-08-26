<?php

namespace App\Services\Storage;

use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

/**
 * Pemrosesan gambar sisi server memakai GD bawaan PHP.
 * Original TIDAK diubah — hanya dibuat salinan turunan:
 * - preview: WebP max 1280px (untuk galeri/PDF)
 * - thumb  : WebP max 320px  (untuk grid/timeline)
 */
class ImageProcessor
{
    public const PREVIEW_MAX = 1280;

    public const THUMB_MAX = 320;

    /**
     * @return array{contents: string, mime: string, extension: string}
     */
    public function makeVariant(Filesystem $disk, string $key, int $maxDimension): array
    {
        $source = $disk->get($key);
        $image = @imagecreatefromstring($source);
        throw_unless($image !== false, RuntimeException::class, 'Gambar tidak dapat diproses.');

        // Orientasi normalisasi hanya pada SALINAN (original tetap utuh).
        if (function_exists('exif_read_data')) {
            $orientation = @exif_read_data('data://image/jpeg;base64,'.base64_encode($source))['Orientation'] ?? null;
            $image = match ($orientation ?? 0) {
                3 => imagerotate($image, 180, 0),
                6 => imagerotate($image, -90, 0),
                8 => imagerotate($image, 90, 0),
                default => $image,
            };
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1.0, $maxDimension / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        $ok = function_exists('imagewebp') ? imagewebp($canvas, null, 82) : imagejpeg($canvas, null, 82);
        $contents = (string) ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($image);
        throw_unless($ok, RuntimeException::class, 'Encoding gambar turunan gagal.');

        return [
            'contents' => $contents,
            'mime' => function_exists('imagewebp') ? 'image/webp' : 'image/jpeg',
            'extension' => function_exists('imagewebp') ? 'webp' : 'jpg',
        ];
    }
}
