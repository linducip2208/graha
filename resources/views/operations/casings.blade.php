<x-layouts.app title="Casing Pile"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-3xl font-black">Casing Pile</h1>
<p class="mt-2 text-slate-500">Register casing (milik/sewa): instalasi, ekstraksi, ditinggal di titik, kerusakan, perbaikan, hilang — setiap perpindahan punya riwayat dan biaya tercatat.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<form method="post" action="/admin/casings" class="mt-6 grid gap-3 rounded-2xl border bg-white p-5 no-print">@csrf
<h2 class="font-bold">Daftarkan Casing</h2>
<div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
<input name="code" required placeholder="Kode (CS-001)" class="rounded-xl border p-3">
<input type="number" step=".01" name="diameter_mm" required placeholder="Diameter (mm)" class="rounded-xl border p-3">
<input type="number" step=".001" name="length_m" required placeholder="Panjang (m)" class="rounded-xl border p-3">
<select name="ownership" required class="rounded-xl border p-3"><option value="owned">Milik sendiri</option><option value="rented">Sewa</option></select>
<input name="supplier_name" placeholder="Pemilik/supplier" class="rounded-xl border p-3">
</div>
<button class="w-fit rounded-xl bg-slate-900 px-6 py-3 font-bold text-white">Simpan casing</button>
</form>

<div class="mt-8 space-y-4">@forelse($units as $unit)
<article class="rounded-2xl border bg-white p-5">
<div class="flex flex-wrap items-center justify-between gap-2">
<strong>{{ $unit->code }} · Ø{{ $unit->diameter_mm }} × {{ $unit->length_m }} m · {{ $unit->ownership === 'owned' ? 'milik' : 'sewa' }}</strong>
<x-ui.badge :status="match($unit->status) { 'in_stock' => 'approved', 'installed' => 'open', 'extracted' => 'draft', 'left_in_pile' => 'pending_approval', 'damage_reported' => 'rejected', 'repaired' => 'approved', default => 'closed' }" :label="$unit->status" />
</div>
<p class="mt-1 text-xs text-slate-500">Lokasi: {{ $unit->currentPile?->pile_number ?? 'gudang/titik lain' }} · siklus pakai: {{ $unit->usage_cycle_count }}× · kondisi: {{ $unit->condition }} @if($unit->ownership === 'rented')· biaya sewa Rp {{ number_format((float) $unit->rental_cost_total, 0, ',', '.') }}@endif</p>
<form method="post" action="/admin/casings/{{ $unit->id }}/move" class="mt-2 flex flex-wrap items-center gap-2 no-print">@csrf
<select name="type" required class="rounded-lg border p-1.5 text-xs">
<option value="installed">Instalasi ke titik</option>
<option value="extracted">Ekstraksi</option>
<option value="left_in_pile">Ditinggal di titik (permanent)</option>
<option value="damage_reported">Lapor kerusakan</option>
<option value="repaired">Selesai diperbaiki</option>
<option value="lost">Hilang</option>
</select>
<select name="bored_pile_id" class="rounded-lg border p-1.5 text-xs"><option value="">Titik (jika instalasi)</option>@foreach($piles as $p)<option value="{{ $p->id }}">{{ $p->project?->code }}/{{ $p->pile_number }}</option>@endforeach</select>
<input type="number" step=".01" min="0" name="cost" placeholder="Biaya" class="w-24 rounded-lg border p-1.5 text-xs">
<input name="notes" placeholder="Catatan" class="flex-1 min-w-40 rounded-lg border p-1.5 text-xs">
<button class="font-bold text-sky-700">Catat pergerakan</button>
</form>
<details class="mt-2"><summary class="cursor-pointer text-xs font-bold text-sky-700">Riwayat ({{ $unit->movements->count() }} terakhir)</summary>
<ul class="mt-1 space-y-1 text-xs text-slate-600">@foreach($unit->movements as $m)<li>{{ $m->occurred_at->format('d/m/Y') }} — <strong>{{ str($m->type)->replace('_',' ') }}</strong> {{ $m->pile?->pile_number ? '· '.$m->pile->pile_number : '' }} {{ $m->notes ? '· '.\Illuminate\Support\Str::limit($m->notes, 60) : '' }}</li>@endforeach</ul>
</details>
<form method="post" action="/admin/casings/{{ $unit->id }}/evidence" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2 no-print">@csrf
<input type="file" name="file" accept="image/jpeg,image/png,image/webp" required class="rounded-lg border p-1.5 text-xs">
<button class="font-bold text-violet-700">Lampirkan foto</button>
@if(($evidences[$unit->id] ?? collect())->isNotEmpty())<span class="text-xs text-slate-500">{{ ($evidences[$unit->id])->count() }} foto tersimpan</span>@endif
</form>
@forelse($evidences[$unit->id] ?? [] as $ev)
<div class="mt-2 flex items-center gap-3 rounded-xl bg-slate-50 p-2 text-xs"><img src="{{ route('evidence.file', $ev) }}" alt="{{ $ev->original_name }}" class="h-14 w-14 rounded-lg border object-cover"><div><strong>{{ \Illuminate\Support\Str::limit($ev->original_name, 40) }}</strong><br>{{ $ev->size_kb }} KB · {{ $ev->created_at->format('d/m/Y H:i') }} · {{ $ev->uploader?->name }} <a href="{{ route('evidence.download', $ev) }}" class="font-bold text-sky-700">Unduh</a></div></div>
@empty
@endforelse
</article>
@empty<x-ui.empty icon="archive" title="Belum ada casing" description="Daftarkan casing pertama untuk mulai melacak siklus pemakaiannya." />@endforelse</div>
</section></x-layouts.app>
