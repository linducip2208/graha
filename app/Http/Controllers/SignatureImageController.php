<?php

namespace App\Http\Controllers;

use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SignatureImageController extends Controller
{
    /** Setiap user mengelola gambar tanda tangan basahnya sendiri (PNG transparan). */
    public function upload(Request $request)
    {
        $request->validate([
            'signature' => ['required', 'file', 'mimes:png', 'max:1024', 'dimensions:min_width=100,min_height=40'],
        ], [
            'signature.mimes' => 'Tanda tangan harus berupa PNG (disarankan latar transparan).',
            'signature.max' => 'Ukuran maksimal 1 MB.',
        ]);
        $user = $request->user();
        if ($user->signature_image) {
            Storage::disk('local')->delete($user->signature_image);
        }
        $path = $request->file('signature')->store("signatures/{$user->id}", 'local');
        $user->update(['signature_image' => $path]);

        return back()->with('status', 'Tanda tangan basah tersimpan — akan tampil pada dokumen yang Anda tandatangani.');
    }

    public function download(CurrentCompany $current)
    {
        $user = auth()->user();
        abort_unless($user->signature_image && Storage::disk('local')->exists($user->signature_image), 404);

        return response()->file(Storage::disk('local')->path($user->signature_image), ['Content-Type' => 'image/png']);
    }
}
