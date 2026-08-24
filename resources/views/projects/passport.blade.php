<x-layouts.app title="Pile Passport {{ $pile->pile_number }} — {{ $pile->project->code }}">
<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
<x-ui.page-header title="Digital Pile Passport — {{ $pile->pile_number }}" subtitle="{{ $pile->project->code }} · {{ $pile->project->name }} · Zona {{ $pile->zone?->name ?? '-' }} · Grid {{ $pile->grid_reference ?? '-' }}" status="{{ str($pile->status)->replace('_',' ') }}">
<div class="flex flex-wrap gap-2 no-print">
<a href="{{ route('piles.passport', $pile) }}" class="rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white">Passport</a>
<a href="{{ route('piles.genealogy', $pile) }}" class="rounded-xl border px-4 py-2 text-sm font-semibold">Genealogy</a>
<a href="{{ route('piles.as-built', $pile) }}" class="rounded-xl border px-4 py-2 text-sm font-semibold">As-Built PDF</a>
@can('project.manage')
<form method="post" action="{{ route('piles.as-built.store', $pile) }}" class="inline">@csrf<button class="rounded-xl border px-4 py-2 text-sm font-semibold hover:bg-slate-50">Simpan As-Built ke Registry</button></form>
<form method="post" action="{{ route('piles.dossier.store', $pile) }}" class="inline">@csrf<button class="rounded-xl bg-emerald-700 px-4 py-2 text-sm font-bold text-white">Buat Acceptance Dossier</button></form>
@endcan
</div>
</x-ui.page-header>

<div class="mt-6 grid gap-6 xl:grid-cols-[1fr_280px]">
<div>
    {{-- Identitas & desain --}}
    <x-ui.card>
    <h2 class="font-black">Identitas & Desain</h2>
    <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
        <div><dt class="text-xs uppercase text-slate-400">Nomor Pile</dt><dd class="font-bold">{{ $pile->pile_number }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Public UUID (QR)</dt><dd class="break-all font-mono text-xs">{{ $pile->public_uuid }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Diameter</dt><dd>Ø{{ $pile->diameter_mm }} mm</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Kedalaman Rencana</dt><dd>{{ $pile->planned_depth_m }} m</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Kedalaman Aktual</dt><dd>{{ $pile->actual_depth_m ?? '-' }} m</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Elevasi Platform / Toe / Cut-off</dt><dd>{{ $pile->platform_elevation ?? '-' }} / {{ $pile->design_toe_level ?? '-' }} → {{ $pile->actual_toe_level ?? '-' }} / {{ $pile->cut_off_level ?? '-' }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Koordinat Rencana (X, Y)</dt><dd>{{ $pile->coordinate_x ?? '-' }}, {{ $pile->coordinate_y ?? '-' }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Grade Beton</dt><dd>{{ $pile->concrete_grade ?? '-' }}</dd></div>
        <div><dt class="text-xs uppercase text-slate-400">Metode Drilling / Rig</dt><dd>{{ $pile->drilling_method ?? '-' }} · {{ \App\Models\Equipment::find($pile->rig_equipment_id)?->code ?? '-' }}</dd></div>
    </dl>
    </x-ui.card>

    {{-- Ringkasan volume --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-4">
        <x-ui.stat-card label="Beton Teoretis" value="{{ $pile->theoretical_concrete_m3 ?? '-' }} m³" />
        <x-ui.stat-card label="Beton Aktual" value="{{ $pile->actual_concrete_m3 ?? '-' }} m³" />
        <x-ui.stat-card label="Overbreak" value="{{ $pile->overbreak_percent ?? 0 }}%" hint="{{ $pile->overbreak_exceeded ? 'Melebihi toleransi' : 'Dalam toleransi' }}" />
        <x-ui.stat-card label="Cage / Casing" value="{{ $cages->count() }} / {{ $casings->count() }}" />
    </div>

    {{-- Anomali / kesehatan --}}
    @if($anomalies)
    <div class="mt-4 grid gap-2 sm:grid-cols-2">
        @foreach($anomalies as $flag)
        <div class="rounded-xl border p-3 text-sm {{ $flag['severity'] === 'critical' ? 'border-red-300 bg-red-50 text-red-800' : 'border-amber-300 bg-amber-50 text-amber-800' }}">
            <strong class="font-bold">{{ str($flag['code'])->replace('_',' ')->title() }}:</strong> {{ $flag['detail'] }}
        </div>
        @endforeach
    </div>
    @else
    <p class="mt-4 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-800">Tidak ada anomali terdeteksi pada data yang tersedia.</p>
    @endif

    {{-- Timeline foto per fase (Phase 12) --}}
    <x-ui.card>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-black">Timeline Foto Evidence</h2>
        <span class="text-xs text-slate-400">{{ $photos->count() }} foto · tersimpan di object storage privat</span>
    </div>
    <ol class="mt-4 space-y-5">
        @foreach($timeline as $phase)
        <li class="relative border-l-2 pl-5 {{ $phase['photos']->isNotEmpty() ? 'border-emerald-400' : 'border-slate-200 dark:border-[#1e2c47]' }}">
            <span class="absolute -left-[9px] top-0 h-4 w-4 rounded-full border-2 {{ $phase['photos']->isNotEmpty() ? 'border-emerald-500 bg-emerald-100' : 'border-slate-300 bg-white dark:bg-[#0f1a2e]' }}"></span>
            <h3 class="text-sm font-bold {{ $phase['photos']->isNotEmpty() ? 'text-slate-900 dark:text-white' : 'text-slate-400' }}">{{ $loop->iteration }}. {{ $phase['label'] }} <span class="ml-1 text-xs font-normal text-slate-400">({{ $phase['photos']->count() }})</span></h3>
            @if($phase['photos']->isNotEmpty())
            <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-5 lg:grid-cols-6">
                @foreach($phase['photos'] as $photo)
                <figure class="group relative">
                    <a href="{{ route('files.preview', ['file' => $photo]) }}" target="_blank" title="{{ $photo->caption ?? $photo->original_name }} — {{ $photo->captured_at?->format('d/m/y H:i') ?? $photo->created_at->format('d/m/y H:i') }} oleh {{ $photo->uploader?->name }}">
                        <img src="{{ route('files.preview', ['file' => $photo, 'variant' => 'thumb']) }}" alt="{{ $photo->caption ?? $photo->original_name }}" loading="lazy" class="h-20 w-full rounded-lg border object-cover">
                    </a>
                    <figcaption class="truncate text-[10px] text-slate-400">{{ $photo->sub_category }} · {{ optional($photo->captured_at ?? $photo->created_at)->format('d/m/y') }}</figcaption>
                </figure>
                @endforeach
            </div>
            @endif
        </li>
        @endforeach
    </ol>

    @canany(['project.manage'])
    <details class="mt-5 rounded-xl border p-4 no-print">
        <summary class="cursor-pointer text-sm font-bold">+ Upload Foto Evidence</summary>
        <form method="post" action="{{ route('piles.photos.store', $pile) }}" enctype="multipart/form-data" class="mt-3 grid gap-3 sm:grid-cols-2">
            @csrf
            <label class="text-xs font-bold uppercase text-slate-500">Kategori
                <select name="category" required class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
                    @foreach(\App\Models\StoredFile::PHOTO_CATEGORIES as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
            <label class="text-xs font-bold uppercase text-slate-500">Foto (JPG/PNG/WebP)
                <input type="file" name="file" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required class="mt-1 w-full text-sm font-normal normal-case">
            </label>
            <label class="text-xs font-bold uppercase text-slate-500">Caption
                <input type="text" name="caption" maxlength="300" class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
            </label>
            <label class="text-xs font-bold uppercase text-slate-500">Waktu Ambil (opsional)
                <input type="datetime-local" name="captured_at" class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
            </label>
            <label class="text-xs font-bold uppercase text-slate-500">GPS Latitude (opsional)
                <input type="number" step="any" name="latitude" min="-90" max="90" class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
            </label>
            <label class="text-xs font-bold uppercase text-slate-500">GPS Longitude (opsional)
                <input type="number" step="any" name="longitude" min="-180" max="180" class="mt-1 w-full rounded-xl border-stone-300 text-sm font-normal normal-case">
            </label>
            <div class="sm:col-span-2"><button class="min-h-[44px] w-full rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white sm:w-auto">Simpan ke Object Storage</button></div>
        </form>
    </details>
    @endcanany
    </x-ui.card>

    {{-- Dokumen pile --}}
    <x-ui.card>
    <h2 class="font-black">Dokumen & Laporan</h2>
    <ul class="mt-2 space-y-1 text-sm">
        @forelse($documents as $doc)
        <li class="flex flex-wrap items-center justify-between gap-2 rounded-xl border p-2 text-xs">
            <span><strong class="uppercase">{{ str($doc->category)->replace('_',' ') }}</strong>@if($doc->documentVersion?->document) · {{ $doc->documentVersion->document->number }} v{{ $doc->documentVersion->version }}@endif · {{ $doc->original_name }}</span>
            <span class="flex items-center gap-2 text-slate-400">
                <span class="font-mono" title="SHA-256">{{ substr($doc->sha256, 0, 12) }}…</span>
                <a href="{{ route('files.download', $doc) }}" class="font-bold text-slate-700 hover:underline">Unduh</a>
            </span>
        </li>
        @empty
        <li class="text-slate-400">Belum ada as-built/dossier yang disimpan ke registry.</li>
        @endforelse
    </ul>
    </x-ui.card>

    {{-- Bore log ringkas --}}
    <x-ui.card>
    <h2 class="font-black">Soil / Bore Log</h2>
    @foreach($drillings as $drilling)
    <details class="mt-2 rounded-xl border p-3" @if($loop->first) open @endif>
        <summary class="cursor-pointer text-sm font-bold">{{ $drilling->drilling_started_at?->format('d/m/Y H:i') ?? '-' }} — {{ strtoupper($drilling->status) }}{{ $drilling->verifier ? ' · diverifikasi '.$drilling->verifier->name : '' }}</summary>
        <table class="mt-2 w-full text-xs"><thead><tr><th>Dari (m)</th><th>Ke (m)</th><th>Deskripsi Tanah</th></tr></thead><tbody>
        @foreach($drilling->layers as $layer)<tr class="border-t"><td>{{ $layer->depth_from_m }}</td><td>{{ $layer->depth_to_m }}</td><td>{{ $layer->soil_description }}</td></tr>@endforeach
        </tbody></table>
    </details>
    @endforeach
    @if($drillings->isEmpty())<p class="mt-2 text-sm text-slate-400">Belum ada drilling record.</p>@endif
    </x-ui.card>
</div>

{{-- Kolom kanan: QR + aktivitas --}}
<aside class="space-y-4">
    <x-ui.card>
    <h2 class="font-black">QR Passport</h2>
    <div class="mx-auto mt-3 w-fit max-w-full overflow-hidden rounded-xl border bg-white [&_svg]:h-48 [&_svg]:w-48">{!! $qrSvg !!}</div>
    <p class="mt-2 break-all text-center font-mono text-[10px] text-slate-400">/piles/{{ $pile->public_uuid }}</p>
    <p class="mt-1 text-center text-xs text-slate-500">Scan di lapangan → login → langsung ke passport pile ini. Akses tetap ber-otorisasi.</p>
    </x-ui.card>
    <x-ui.card>
    <h2 class="font-black">Linimasa Status</h2>
    <ol class="mt-2 space-y-1 text-xs">
        @foreach($activities as $a)<li class="rounded-lg border p-2"><span class="font-mono">{{ optional($a->started_at)->format('d/m H:i') ?? '?' }}</span> {{ str($a->from_status)->replace('_',' ')->title() }} → <strong>{{ str($a->to_status)->replace('_',' ')->title() }}</strong></li>
        @endforeach
        @if($activities->isEmpty())<li class="text-slate-400">Belum ada aktivitas.</li>@endif
    </ol>
    </x-ui.card>
</aside>
</div>
</section></x-layouts.app>
