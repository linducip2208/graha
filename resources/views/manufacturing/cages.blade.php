<x-layouts.app title="Reinforcement Cage"><div class="page-container">
<x-ui.page-header title="Reinforcement Cage" />
<div class="mt-6 grid grid-cols-2 gap-3 xl:grid-cols-3">
<x-ui.stat-card label="Total Cage" value="{{ number_format($cages->count()) }}" icon="grid" tone="brand" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Menunggu QC" value="{{ number_format($cages->where('qc_status', 'draft')->count()) }}" icon="clock" tone="{{ $cages->where('qc_status', 'draft')->isNotEmpty() ? 'warning' : 'success' }}" :value-class="'text-[24px] leading-tight'" />
<x-ui.stat-card label="Berat Teoretis Total" value="{{ number_format((float) $cages->sum('theoretical_weight_kg'), 0, ',', '.') }} kg" icon="archive" tone="info" :value-class="'text-[18px] leading-tight'" />
</div>

<p class="mt-2 text-slate-500">Siklus cage tulangan: fabrikasi → timbangan (varians baja vs toleransi {{ $tolerance }}%) → QC independen → pengiriman ke titik pile yang siap menerima.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
@if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

<form method="post" action="/admin/manufacturing/cages" class="mt-6 grid gap-3 rounded-2xl border bg-white p-5 no-print">@csrf
<h2 class="font-bold">Fabrikasi Cage Baru</h2>
<div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
<input name="number" required placeholder="Nomor cage (CAGE-001)" class="rounded-xl border p-3">
<input name="design_ref" placeholder="Ref. gambar/desain" class="rounded-xl border p-3">
<input type="number" step=".01" name="diameter_mm" required placeholder="Diameter (mm)" class="rounded-xl border p-3">
<input type="number" step=".001" name="total_length_m" required placeholder="Panjang total (m)" class="rounded-xl border p-3">
<input type="number" min="1" name="segment_count" value="1" placeholder="Segmen" class="rounded-xl border p-3">
<input name="main_bar_spec" placeholder="Tulangan utama (mis. 20D16)" class="rounded-xl border p-3">
<input name="spiral_spec" placeholder="Spiral (8mm@150)" class="rounded-xl border p-3">
<input name="stiffener_spec" placeholder="Pengaku" class="rounded-xl border p-3">
<input type="number" min="0" name="coupler_count" placeholder="Jumlah coupler" class="rounded-xl border p-3">
<input type="number" step=".01" name="theoretical_weight_kg" placeholder="Berat teoretis (kg)" class="rounded-xl border p-3">
<input name="heat_number" placeholder="Heat number" class="rounded-xl border p-3">
<input name="mill_cert_number" placeholder="No. mill cert" class="rounded-xl border p-3">
<input name="storage_location" placeholder="Lokasi penyimpanan" class="rounded-xl border p-3 xl:col-span-2">
</div>
<button class="w-fit rounded-xl bg-[var(--brand-primary)] px-6 py-3 font-bold text-white">Daftarkan cage</button>
</form>

<h2 class="mt-10 text-lg font-black">Daftar Cage</h2>
<div class="mt-3 overflow-x-auto rounded-2xl border bg-white"><table class="w-full min-w-[980px] text-sm table-sticky"><thead><tr><th>Nomor</th><th>Diameter/Panjang</th><th>Spec Utama</th><th>Heat/Cert</th><th class="text-right">Teoretis (kg)</th><th class="text-right">Aktual (kg)</th><th class="text-right">Varian</th><th>Titik Tujuan</th><th>Status QC</th></tr></thead><tbody>
@if($cages->isEmpty())
<tr><td colspan="9" class="p-8 text-center">Belum ada cage.</td></tr>
@endif
@foreach($cages as $cage)
@php($variance = $cage->weightVariancePercent())
@php($qcBadge = $cage->qc_status === 'passed' ? 'approved' : ($cage->qc_status === 'failed' ? 'rejected' : 'draft'))
@php($varianceClass = ($variance !== null && abs($variance) > (float) $tolerance) ? 'text-red-600 font-bold' : '')
<tr>
<td class="font-mono text-xs">{{ $cage->number }}<span class="block text-slate-400">{{ $cage->design_ref }}</span></td>
<td>{{ $cage->diameter_mm }} mm · {{ $cage->total_length_m }} m</td>
<td>{{ $cage->main_bar_spec ?? '-' }}<span class="block text-slate-400">{{ $cage->spiral_spec }}</span></td>
<td class="text-xs">{{ $cage->heat_number ?? '-' }}<span class="block text-slate-400">{{ $cage->mill_cert_number }}</span></td>
<td class="text-right font-mono">{{ $cage->theoretical_weight_kg ?? '-' }}</td>
<td class="text-right font-mono">{{ $cage->actual_weight_kg ?? '-' }}</td>
<td class="text-right font-mono {{ $varianceClass }}">{{ $variance === null ? '-' : $variance.'%' }}</td>
<td>{{ $cage->pile?->pile_number ?? '-' }}@if($cage->delivered_at)<span class="block text-slate-400">{{ $cage->delivered_at->format('d/m/Y') }}</span>@endif</td>
<td><x-ui.badge :status="$qcBadge" :label="$cage->qc_status" />@if($cage->qc_by)<span class="ml-1 text-[10px] text-slate-400">oleh {{ \App\Models\User::find($cage->qc_by)?->name }}</span>@endif</td>
</tr>
@endforeach
</tbody></table></div>

<h2 class="mt-10 text-lg font-black">Aksi QC & Pengiriman</h2>
<div class="mt-3 space-y-3">@foreach($cages as $cage)
@php($canManage = auth()->user()->hasPermission('manufacturing.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
<article class="rounded-2xl border bg-white p-4">
<div class="flex flex-wrap items-center justify-between gap-2">
<strong>{{ $cage->number }}</strong>
<x-ui.badge :status="$cage->qc_status === 'passed' ? 'approved' : ($cage->qc_status === 'failed' ? 'rejected' : 'draft')" />
</div>
@if($canManage)
@if($cage->qc_status === 'draft' && $cage->created_by !== auth()->id())
<form method="post" action="/admin/manufacturing/cages/{{ $cage->id }}/qc" class="mt-2 flex flex-wrap items-center gap-2">@csrf
<input type="hidden" name="result" value="passed"><input type="number" step=".01" name="actual_weight_kg" required placeholder="Berat aktual (kg)" class="w-40 rounded-lg border p-1.5 text-xs"><input name="qc_notes" placeholder="Catatan QC" class="flex-1 rounded-lg border p-1.5 text-xs"><button data-confirm="Rekam QC lolos dengan berat aktual ini?" class="font-bold text-emerald-700">QC Lolos</button></form>
<form method="post" action="/admin/manufacturing/cages/{{ $cage->id }}/qc" class="inline-flex gap-1">@csrf<input type="hidden" name="result" value="failed"><button data-confirm="Tandai cage GAGAL QC?" class="font-bold text-red-600">Gagal QC</button></form>
@endif
@if($cage->qc_status === 'passed')
<form method="post" action="/admin/manufacturing/cages/{{ $cage->id }}/deliver" class="mt-2 flex flex-wrap items-center gap-2">@csrf
<select name="bored_pile_id" required class="rounded-lg border p-1.5 text-xs"><option value="">Kirim ke titik siap pasang</option>@foreach($piles as $pile)<option value="{{ $pile->id }}">{{ $pile->project?->code }}/{{ $pile->pile_number }} ({{ str($pile->status)->replace('_',' ') }})</option>@endforeach</select>
<button data-confirm="Catat pengiriman cage ke titik ini?" class="font-bold text-[var(--brand-primary)]">Catat pengiriman</button></form>
@endif
@endif
<form method="post" action="/admin/manufacturing/cages/{{ $cage->id }}/material" class="mt-2 flex flex-wrap items-center gap-2 no-print">@csrf
<select name="item_id" required class="rounded-lg border p-1.5 text-xs"><option value="">Material baja</option>@foreach($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select>
<select name="warehouse_bin_id" required class="rounded-lg border p-1.5 text-xs"><option value="">Gudang / Bin</option>@foreach($warehouses as $wh)@foreach($wh->bins as $bin)
<option value="{{ $bin->id }}">{{ $wh->code }} / {{ $bin->code }}</option>
@endforeach
@endforeach</select>
<input type="number" step=".0001" min="0.0001" name="quantity_kg" required placeholder="Qty (kg)" class="w-24 rounded-lg border p-1.5 text-xs">
<input name="lot_number" placeholder="Heat/lot (ops.)" class="w-28 rounded-lg border p-1.5 text-xs">
<input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
<button data-confirm="Bebankan material ini ke cage? Stok berkurang dan jurnal biaya diposting." class="font-bold text-amber-700">Bebankan material</button>
</form>
<form method="post" action="/admin/manufacturing/cages/{{ $cage->id }}/evidence" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2 no-print">@csrf
<input type="file" name="file" accept="image/jpeg,image/png,image/webp" required class="rounded-lg border p-1.5 text-xs">
<button class="font-bold text-violet-700">Lampirkan foto</button>
@if(($evidences[$cage->id] ?? collect())->isNotEmpty())<span class="text-xs text-slate-500">{{ ($evidences[$cage->id])->count() }} foto tersimpan</span>@endif
</form>
@forelse($evidences[$cage->id] ?? [] as $ev)
<div class="mt-2 flex items-center gap-3 rounded-xl bg-slate-50 p-2 text-xs"><img src="{{ route('evidence.file', $ev) }}" alt="{{ $ev->original_name }}" class="h-14 w-14 rounded-lg border object-cover"><div><strong>{{ \Illuminate\Support\Str::limit($ev->original_name, 40) }}</strong><br>{{ $ev->size_kb }} KB · {{ $ev->created_at->format('d/m/Y H:i') }} · {{ $ev->uploader?->name }} <a href="{{ route('evidence.download', $ev) }}" class="font-bold text-[var(--brand-primary)]">Unduh</a></div></div>
@empty
@endforelse
</article>
@endforeach</div>
</div></x-layouts.app>
