<x-layouts.app title="Tools Check-out"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Tools Check-out / Check-in</h1>
<p class="mt-2 text-slate-500">Kartu kendali alat bantu: siapa meminjam, kapan harus kembali, kondisi saat kembali, dan pelaporan kehilangan.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<form method="post" action="/admin/tools" class="mt-6 grid gap-3 rounded-2xl border bg-white p-5 no-print">@csrf
<h2 class="font-bold">Daftarkan Alat</h2>
<div class="grid gap-2 sm:grid-cols-4"><input name="code" required placeholder="Kode (TL-001)" class="rounded-xl border p-3"><input name="name" required placeholder="Nama alat" class="rounded-xl border p-3"><input name="category" placeholder="Kategori" class="rounded-xl border p-3"><input type="number" step=".01" name="purchase_cost" placeholder="Harga perolehan" class="rounded-xl border p-3"></div>
<button class="w-fit rounded-xl bg-slate-900 px-6 py-3 font-bold text-white">Simpan alat</button>
</form>

<h2 class="mt-10 text-lg font-black">Daftar Alat</h2>
<div class="mt-3 space-y-4">@forelse($tools as $tool)
<x-ui.card>
<div class="flex flex-wrap items-center justify-between gap-2">
<strong>{{ $tool->code }} — {{ $tool->name }}</strong>
<x-ui.badge :status="$tool->status === 'available' ? 'approved' : ($tool->status === 'checked_out' ? 'pending_approval' : ($tool->status === 'lost' ? 'rejected' : 'closed'))" :label="$tool->status" />
</div>
<p class="mt-1 text-xs text-slate-500">@if($tool->holder)Dipegang: <strong>{{ $tool->holder->name }}</strong> sejak {{ optional($tool->checked_out_at)->format('d/m/Y') }} · harus kembali {{ optional($tool->expected_return_at)->format('d/m/Y') ?? '-' }}@elseTersedia di gudang.@endif</p>

<form method="post" action="/admin/tools/{{ $tool->id }}/checkout" class="mt-2 flex flex-wrap items-end gap-2 no-print">@csrf
<input type="hidden" name="_method" value="">
<select name="user_id" required class="rounded-lg border p-1.5 text-xs"><option value="">Serahkan ke</option>@foreach($members as $m)<option value="{{ $m->id }}">{{ $m->name }}</option>@endforeach</select>
<select name="project_id" class="rounded-lg border p-1.5 text-xs"><option value="">Proyek (opsional)</option>@foreach($projects as $prj)<option value="{{ $prj->id }}">{{ $prj->code }}</option>@endforeach</select>
<input type="date" name="expected_return_at" class="rounded-lg border p-1.5 text-xs">
<button @disabled($tool->status !== 'available') class="font-bold text-[var(--brand-primary)] disabled:opacity-40">Check-out</button>
</form>
<form method="post" action="/admin/tools/{{ $tool->id }}/checkin" class="inline-flex flex-wrap items-end gap-2 no-print">@csrf
<input name="condition_note" placeholder="Catatan kondisi saat kembali" class="w-48 rounded-lg border p-1.5 text-xs">
<button @disabled($tool->status !== 'checked_out') class="font-bold text-emerald-700 disabled:opacity-40">Check-in</button>
</form>
<form method="post" action="/admin/tools/{{ $tool->id }}/lost" data-confirm="Tandai alat {{ $tool->code }} sebagai HILANG? Tindakan tercatat di audit trail dan tidak dapat dibatalkan." class="inline-flex flex-wrap items-end gap-2 no-print">@csrf
<input name="lost_note" required placeholder="Alasan hilang" class="w-36 rounded-lg border p-1.5 text-xs">
<button data-confirm="Laporkan alat ini HILANG? Status tidak dapat dikembalikan." @disabled($tool->status === 'lost') class="font-bold text-red-600 disabled:opacity-40">Hilang</button>
</form>

<form method="post" action="/admin/tools/{{ $tool->id }}/evidence" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2 no-print">@csrf
<input type="file" name="file" accept="image/jpeg,image/png,image/webp" required class="rounded-lg border p-1.5 text-xs">
<button class="font-bold text-violet-700">Lampirkan foto</button>
@if(($evidences[$tool->id] ?? collect())->isNotEmpty())<span class="text-xs text-slate-500">{{ ($evidences[$tool->id])->count() }} foto tersimpan</span>@endif
</form>
@forelse($evidences[$tool->id] ?? [] as $ev)
<div class="mt-2 flex items-center gap-3 rounded-xl bg-slate-50 p-2 text-xs"><img src="{{ route('evidence.file', $ev) }}" alt="{{ $ev->original_name }}" class="h-14 w-14 rounded-lg border object-cover"><div><strong>{{ \Illuminate\Support\Str::limit($ev->original_name, 40) }}</strong><br>{{ $ev->size_kb }} KB · {{ $ev->created_at->format('d/m/Y H:i') }} · {{ $ev->uploader?->name }} <a href="{{ route('evidence.download', $ev) }}" class="font-bold text-[var(--brand-primary)]">Unduh</a></div></div>
@empty
@endforelse

<details class="mt-2"><summary class="cursor-pointer text-xs font-bold text-[var(--brand-primary)]">Riwayat</summary>
<ul class="mt-1 space-y-1 text-xs text-slate-600">@foreach($tool->movements as $mv)<li>{{ $mv->occurred_at->format('d/m/Y H:i') }} — <strong>{{ str($mv->type)->replace('_',' ') }}</strong> oleh {{ $mv->holder?->name }} {{ $mv->notes ? '· '.\Illuminate\Support\Str::limit($mv->notes, 60) : '' }}</li>@endforeach</ul>
</details>
</x-ui.card>
@empty<x-ui.empty icon="wrench" title="Belum ada alat terdaftar" description="Daftarkan alat bantu pertama untuk mulai kontrol keluar-masuk." />@endforelse</div>
</section></x-layouts.app>
