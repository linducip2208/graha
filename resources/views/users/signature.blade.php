<x-layouts.app title="Tanda Tangan Saya"><section class="mx-auto max-w-2xl px-4 py-10">
<h1 class="text-3xl font-black">Tanda Tangan Saya</h1>
<p class="mt-2 text-slate-600">Halo {{ $user->name }} — unggah gambar tanda tangan basah Anda sekali saja. Setelah tersimpan, tanda tangan ini otomatis muncul pada faktur dan dokumen yang Anda tandatangani di sistem.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-8 grid gap-5 sm:grid-cols-2">
<form method="post" action="/admin/my-signature" enctype="multipart/form-data" class="grid gap-3 rounded-2xl border bg-white p-6">@csrf
<h2 class="font-bold">Unggah / Ganti</h2>
<input type="file" name="signature" accept=".png" required class="rounded-xl border p-3 text-sm">
<ul class="list-disc space-y-1 pl-5 text-xs text-slate-500"><li>Format PNG, latar transparan</li><li>Maksimal 1 MB</li><li>Minimal 100×40 px</li></ul>
<button class="rounded-xl bg-sky-700 p-3 font-bold text-white">Simpan tanda tangan</button>
</form>
<div class="rounded-2xl border bg-white p-6">
<h2 class="font-bold">Tanda Tangan Saat Ini</h2>
@if($user->signature_image)
<img src="/admin/my-signature/image" alt="Tanda tangan {{ $user->name }}" style="max-height:90px" class="mt-3 rounded-lg border bg-white p-2">
@else
<p class="mt-3 text-sm text-slate-500">Belum ada. Unggah dari form di samping.</p>
@endif
</div>
</div>

<a href="/dashboard" class="mt-8 inline-block text-sm font-bold text-sky-700">← Kembali ke dashboard</a>
</section></x-layouts.app>
