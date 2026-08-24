<x-layouts.app title="Tangki BBM & Rekonsiliasi"><section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Tangki BBM & Rekonsiliasi</h1>
<p class="mt-2 text-slate-500">Kartu stok tangki solar: penerimaan, pengeluaran ke alat, dan rekonsiliasi fisik — selisih otomatis dicatat sebagai penyesuaian ter-audit.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">@forelse($tanks as $t)
<article class="card-lift rounded-2xl border bg-white p-5 {{ $tank?->id === $t->id ? 'ring-2 ring-sky-600' : '' }}">
<strong>{{ $t->code }} — {{ $t->name }}</strong>
<p class="mt-2 text-2xl font-black tabular-nums">{{ number_format((float) $t->balance, 0, ',', '.') }} L</p>
<p class="text-xs text-slate-500">Kapasitas {{ number_format((float) $t->capacity_l, 0, ',', '.') }} L</p>
<a href="/admin/fuel-tanks?tank={{ $t->id }}" class="mt-2 inline-block text-xs font-bold text-[var(--brand-primary)]">Kelola →</a>
</article>@empty<x-ui.empty icon="wrench" title="Belum ada tangki" description="Daftarkan tangki solar utama untuk mulai pencatatan." />@endforelse</div>

<form method="post" action="/admin/fuel-tanks" class="mt-8 grid gap-3 rounded-2xl border bg-white p-5 no-print">@csrf
<h2 class="font-bold">Daftarkan Tangki</h2>
<div class="grid gap-2 sm:grid-cols-4"><input name="code" required placeholder="Kode (TK-01)" class="rounded-xl border p-3"><input name="name" required placeholder="Nama tangki" class="rounded-xl border p-3"><input type="number" step=".01" name="capacity_l" required placeholder="Kapasitas (L)" class="rounded-xl border p-3"><input type="number" step=".01" min="0" name="opening_liters" placeholder="Saldo awal (L)" class="rounded-xl border p-3"></div>
<button class="w-fit rounded-xl bg-slate-900 px-6 py-3 font-bold text-white">Simpan tangki</button>
</form>

@if($tank)
<form method="post" action="/admin/fuel-tanks/{{ $tank->id }}/record" class="mt-8 grid gap-3 rounded-2xl border bg-white p-5 no-print">@csrf
<h2 class="font-black">{{ $tank->code }} — Transaksi Baru <span class="text-sm font-normal text-slate-500">(saldo buku: {{ number_format((float) $balance, 2, ',', '.') }} L)</span></h2>
<div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
<select name="type" required class="rounded-xl border p-3"><option value="receipt">Penerimaan (beli/terima)</option><option value="issue_to_equipment">Pengisian ke alat</option><option value="issue_other">Pengeluaran lain</option><option value="reading_adjustment">Penyesuaian pembacaan</option></select>
<input type="number" step=".01" name="liters" required placeholder="Liter" class="rounded-xl border p-3">
<select name="equipment_id" class="rounded-xl border p-3"><option value="">Alat (untuk issue ke alat)</option>@foreach($equipments as $e)<option value="{{ $e->id }}">{{ $e->code }} — {{ $e->name }}</option>@endforeach</select>
<input name="reference" placeholder="Referensi (surat jalan/dispetch)" class="rounded-xl border p-3">
<input name="idempotency_key" required value="ft-{{ now()->format('YmdHis') }}-{{ rand(100,999) }}" class="rounded-xl border p-3 font-mono text-xs">
</div>
<button class="w-fit rounded-xl bg-[var(--brand-primary)] px-6 py-3 font-bold text-white">Catat transaksi</button>
</form>

<form method="post" action="/admin/fuel-tanks/{{ $tank->id }}/reconcile" class="mt-4 grid gap-3 rounded-2xl border bg-white p-5 no-print">@csrf
<h3 class="text-sm font-bold">Rekonsiliasi Fisik (stik/pembacaan meter)</h3>
<div class="flex flex-wrap items-center gap-2"><input type="number" step=".01" name="reading" required placeholder="Volume fisik terbaca (L)" class="w-64 rounded-xl border p-3"><button data-confirm="Selisih buku vs fisik akan dicatat sebagai penyesuaian permanen. Lanjutkan?" class="rounded-xl bg-amber-600 px-5 py-3 font-bold text-white">Rekonsiliasi sekarang</button></div>
</form>

<h2 class="mt-10 text-lg font-black">Kartu Stok {{ $tank->code }}</h2>
<div class="mt-3 overflow-x-auto rounded-2xl border bg-white"><table class="w-full min-w-[720px] text-sm table-sticky"><thead><tr><th>Waktu</th><th>Jenis</th><th>Alat/Proyek</th><th>Referensi</th><th class="text-right">Liter (+/-)</th></tr></thead><tbody>@forelse($transactions as $tr)
<tr><td>{{ $tr->occurred_at->format('d/m/Y H:i') }}</td><td>{{ str($tr->type)->replace('_',' ') }}</td><td>{{ $tr->equipment?->code ?? $tr->project?->code ?? '-' }}</td><td>{{ $tr->reference ?? '-' }}</td><td class="text-right font-mono {{ str_starts_with((string) $tr->liters, '-') ? 'text-red-600' : 'text-emerald-700' }}">{{ $tr->liters }}</td></tr>
@empty<tr><td colspan="5" class="p-8 text-center">Belum ada transaksi.</td></tr>@endforelse</tbody></table></div>
@endif
</section></x-layouts.app>
