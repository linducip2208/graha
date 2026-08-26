<x-layouts.app title="Object Storage — Dashboard">
<div class="page-container">
<x-ui.page-header title="Object Storage" subtitle="Dashboard agregat dari metadata registry — tanpa bucket scan; binary tidak pernah disentuh untuk statistik." status="{{ $projectFilter ? 'filter proyek' : 'semua proyek' }}">
<div class="flex flex-wrap gap-2 no-print">
<form method="get" class="flex gap-2">
    <select name="project" onchange="this.form.submit()" class="rounded-xl border p-2.5 text-sm">
        <option value="">Semua proyek</option>
        @foreach($projects as $project)
        <option value="{{ $project->id }}" @selected($projectFilter?->id === $project->id)>{{ $project->code }}</option>
        @endforeach
    </select>
</form>
</div>
</x-ui.page-header>

<div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Objek</p><p class="mt-2 text-3xl font-black tabular-nums">{{ number_format((int) $totals->objects) }}</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Total Ukuran</p><p class="mt-2 text-3xl font-black tabular-nums">{{ number_format((float) $totals->bytes / 1048576, 1) }} MB</p></div>
    @foreach(collect(['photo' => 'Foto', 'document' => 'Dokumen', 'as_built' => 'As-Built', 'dossier' => 'Dossier', 'handover' => 'Handover']) as $cat => $label)
    @php($row = $byCategory->firstWhere('category', $cat))
    <div class="rounded-2xl border bg-slate-50 p-5 shadow-sm {{ in_array($cat, ['as_built', 'handover']) ? 'ring-1 ring-emerald-200' : '' }}"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">{{ $label }}{{ in_array($cat, ['as_built', 'dossier', 'handover']) ? ' 🔒' : '' }}</p><p class="mt-2 text-xl font-black tabular-nums">{{ number_format((int) ($row->objects ?? 0)) }}</p><p class="text-[10px] text-slate-400">{{ number_format((float) ($row->bytes ?? 0) / 1048576, 1) }} MB</p></div>
    @endforeach
</div>

<div class="mt-6 grid gap-4 lg:grid-cols-3">
    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <h2 class="text-sm font-black">Status Lifecycle</h2>
        <ul class="mt-3 space-y-1.5 text-xs">
            @forelse($byStatus as $row)
            <li class="flex justify-between rounded-lg border px-3 py-1.5 {{ $row->status === 'pending_delete' ? 'border-red-300 bg-red-50 text-red-700' : ($row->status === 'archived' ? 'border-amber-300 bg-amber-50 text-amber-800' : '') }}">
                <span class="font-bold uppercase">{{ str($row->status)->replace('_', ' ') }}</span><span class="font-mono">{{ number_format((int) $row->objects) }}</span>
            </li>
            @empty
            <li class="text-slate-400">Belum ada file.</li>
            @endforelse
        </ul>
    </div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <h2 class="text-sm font-black">Per Disk</h2>
        <ul class="mt-3 space-y-1.5 text-xs">
            @forelse($byDisk as $row)
            <li class="flex justify-between rounded-lg border px-3 py-1.5"><span class="font-mono font-bold">{{ $row->disk }}</span><span>{{ number_format((int) $row->objects) }} · {{ number_format((float) $row->bytes / 1048576, 1) }} MB</span></li>
            @empty
            <li class="text-slate-400">Belum ada data.</li>
            @endforelse
        </ul>
        <p class="mt-3 text-[11px] text-slate-400">Kategori 🔒 (as-built/dossier/handover) dilindungi retensi — tidak dapat dihapus fisik.</p>
    </div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <h2 class="text-sm font-black">Foto per Fase (top)</h2>
        <ul class="mt-3 space-y-1 text-xs">
            @forelse($bySubCategory as $row)
            <li class="flex justify-between rounded-lg bg-slate-50 px-3 py-1"><span>{{ \App\Models\StoredFile::PHOTO_CATEGORIES[$row->sub_category] ?? $row->sub_category }}</span><span class="font-mono">{{ number_format((int) $row->objects) }}</span></li>
            @empty
            <li class="text-slate-400">Belum ada foto evidence.</li>
            @endforelse
        </ul>
    </div>
</div>

@if(! $projectFilter && $byProject->isNotEmpty())
<div class="mt-4 overflow-x-auto rounded-2xl border bg-white shadow-sm">
<table class="w-full min-w-[420px] text-xs table-sticky">
<thead><tr class="border-b bg-slate-50 uppercase tracking-wider"><th class="p-2 text-left">Proyek</th><th class="p-2 text-right">Objek</th><th class="p-2 text-right">Ukuran</th></tr></thead>
<tbody>@foreach($byProject as $row)
@php($p = $projects->firstWhere('id', $row->project_id))
<tr class="border-b hover:bg-indigo-50/40"><td class="p-2 font-bold">{{ $p?->code ?? '-' }} <span class="font-normal text-slate-400">{{ $p?->name }}</span></td><td class="p-2 text-right font-mono">{{ number_format((int) $row->objects) }}</td><td class="p-2 text-right font-mono">{{ number_format((float) $row->bytes / 1048576, 1) }} MB</td></tr>
@endforeach</tbody>
</table>
</div>
@endif
</div></x-layouts.app>
