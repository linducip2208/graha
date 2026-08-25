{{-- Kartu Ready-to-Drill / Ready-to-Cast (ADR-073): hasil deterministic readiness engine. --}}
@php($drill = $readiness['drill'] ?? null)
@php($cast = $readiness['cast'] ?? null)
<div class="mt-4 grid gap-4 lg:grid-cols-2">
    @foreach([['drill', 'Ready to Drill', $drill], ['cast', 'Ready to Cast', $cast]] as [$kind, $title, $check])
    <div class="rounded-2xl border p-4 {{ ($check?->status === 'READY' || $check?->status === 'READY_TO_CAST') ? 'border-emerald-300 bg-emerald-50' : ($check ? 'border-red-300 bg-red-50' : 'border-slate-300 bg-slate-50') }}">
        <div class="flex items-center justify-between gap-2">
            <h3 class="font-black">{{ $title }}</h3>
            @if($check)
            <span class="rounded-md px-2 py-1 text-[11px] font-bold uppercase {{ in_array($check->status, ['READY', 'READY_TO_CAST']) ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white' }}">{{ str($check->status)->replace('_', ' ') }}</span>
            @else
            <span class="rounded-md bg-slate-200 px-2 py-1 text-[11px] font-bold uppercase text-slate-600">Belum dicek</span>
            @endif
        </div>
        @if($check)
        <p class="mt-1 text-[11px] text-slate-500">Terakhir dicek {{ $check->created_at->format('d/m/Y H:i') }} oleh {{ $check->checker?->name ?? '-' }}</p>
        @php($failItems = collect($check->checklist ?? [])->where('state', 'fail')->values())
        @if(count($check->blockers ?? []) > 0)
        <ul class="mt-2 space-y-1 text-xs text-red-800">
            @foreach($failItems->take(6) as $item)
            <li class="flex gap-1"><span aria-hidden="true">✗</span><span>{{ $item['label'] }}@if($item['detail']) — {{ $item['detail'] }}@endif</span></li>
            @endforeach
            @if($failItems->count() > 6)<li class="font-semibold">+{{ $failItems->count() - 6 }} blocker lainnya…</li>@endif
        </ul>
        @else
        <p class="mt-2 text-xs font-semibold text-emerald-700">Semua checklist lolos.</p>
        @endif
        <details class="mt-2">
            <summary class="cursor-pointer text-[11px] font-semibold text-slate-500">Detail checklist</summary>
            <div class="mt-1 grid gap-1">
                @foreach(collect($check->checklist ?? []) as $item)
                <div class="flex items-start gap-1 rounded-lg border px-2 py-0.5 text-[11px] {{ $item['state'] === 'pass' ? 'border-emerald-200 bg-emerald-50/60' : ($item['state'] === 'fail' ? 'border-red-200 bg-red-50/60' : 'border-slate-200 opacity-60') }}">
                    <span>{{ $item['state'] === 'pass' ? '✓' : ($item['state'] === 'fail' ? '✗' : '–') }}</span>
                    <span>{{ $item['label'] }}@if($item['detail']) — <span class="text-slate-500">{{ $item['detail'] }}</span>@endif</span>
                </div>
                @endforeach
            </div>
        </details>
        @else
        <p class="mt-2 text-xs text-slate-500">Jalankan pengecekan untuk menilai kesiapan dari data nyata.</p>
        @endif
        @can('project.manage')
        <form method="post" action="{{ route('piles.readiness.check', $pile) }}" class="mt-3">@csrf
            <input type="hidden" name="kind" value="{{ $kind }}">
            <button class="min-h-[38px] rounded-xl border border-slate-400 bg-white px-3 py-1.5 text-xs font-bold hover:bg-slate-100">Cek Ulang</button>
        </form>
        @endcan
    </div>
    @endforeach
</div>

{{-- Attestasi lapangan --}}
@can('project.manage')
<div class="mt-2 flex flex-wrap gap-2 no-print">
    @unless($pile->platform_ready_at)
    <form method="post" action="{{ route('piles.attest', $pile) }}">@csrf<input type="hidden" name="attestation" value="platform"><button class="rounded-xl border px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">Konfirmasi Platform Siap</button></form>
    @endunless
    @unless($pile->concrete_booking_confirmed_at || $pile->deliveries()->exists())
    <form method="post" action="{{ route('piles.attest', $pile) }}">@csrf<input type="hidden" name="attestation" value="concrete_booking"><button class="rounded-xl border px-3 py-1.5 text-xs font-semibold hover:bg-slate-50">Konfirmasi Booking Beton</button></form>
    @endunless
</div>
@endcan

{{-- Bottom cleaning inspection gate (opsional) --}}
<x-ui.card class="mt-4">
<h2 class="font-black">Bottom Cleaning Inspection</h2>
<p class="text-xs text-slate-400">Gate inspeksi cleaning hanya wajib bila company mengaktifkan <code>require_cleaning_inspection</code>; tanpa gate, sediment tetap terekam sebagai data.</p>
<ul class="mt-2 space-y-1 text-xs">
    @forelse($cleaningInspections as $inspection)
    <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-2">
        <span>{{ $inspection->inspected_at?->format('d/m/Y H:i') }} · {{ str($inspection->method)->replace('_', ' ') }} · sediment {{ $inspection->sediment_thickness_mm ?? '-' }} mm · oleh {{ $inspection->inspector?->name ?? '-' }}</span>
        <span class="flex items-center gap-2">
            <span class="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase {{ ['accepted' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-red-100 text-red-700'][$inspection->status] ?? 'bg-slate-100 text-slate-600' }}">{{ $inspection->status }}</span>
            @can('qms.verify')
            @if($inspection->status === 'pending')
            <span class="flex gap-1">
                <form method="post" action="{{ route('piles.cleaning.decide', $inspection) }}">@csrf<input type="hidden" name="decision" value="accepted"><button class="rounded-lg border border-emerald-400 px-2 py-1 font-bold text-emerald-700">Accept</button></form>
                <form method="post" action="{{ route('piles.cleaning.decide', $inspection) }}">@csrf<input type="hidden" name="decision" value="rejected"><button class="rounded-lg border border-red-400 px-2 py-1 font-bold text-red-700">Reject</button></form>
            </span>
            @endif
            @endcan
        </span>
    </li>
    @empty
    <li class="text-slate-400">Belum ada record inspeksi bottom cleaning.</li>
    @endforelse
</ul>
@can('project.manage')
<details class="mt-3 rounded-xl border p-3 no-print">
    <summary class="cursor-pointer text-sm font-bold">+ Record Bottom Cleaning Inspection</summary>
    <form method="post" action="{{ route('piles.cleaning.store', $pile) }}" class="mt-3 grid gap-3 sm:grid-cols-3">
        @csrf
        <label class="text-xs font-bold uppercase text-slate-500">Metode
            <select name="method" required class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
                @foreach(\App\Models\PileBottomCleaningInspection::METHODS as $method)
                <option value="{{ $method }}">{{ str($method)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-bold uppercase text-slate-500">Sediment (mm)
            <input type="number" name="sediment_thickness_mm" step="0.01" min="0" class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
        </label>
        <label class="text-xs font-bold uppercase text-slate-500">Waktu Cleaning
            <input type="datetime-local" name="cleaned_at" class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
        </label>
        <label class="text-xs font-bold uppercase text-slate-500">Waktu Inspeksi
            <input type="datetime-local" name="inspected_at" required class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
        </label>
        <label class="text-xs font-bold uppercase text-slate-500">Witness (opsional)
            <select name="witnessed_by" class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
                <option value="">—</option>
                @foreach($companyUsers as $user)
                <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </label>
        <label class="text-xs font-bold uppercase text-slate-500 sm:col-span-3">Catatan
            <input type="text" name="notes" maxlength="2000" class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
        </label>
        <div class="sm:col-span-3"><button class="min-h-[44px] rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Simpan Record Cleaning</button></div>
    </form>
</details>
@endcan
</x-ui.card>
