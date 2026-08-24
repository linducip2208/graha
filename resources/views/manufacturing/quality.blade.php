<x-layouts.app title="Quality Control Produksi">
    <div class="page-container">
        <a href="/admin/manufacturing" class="text-sm font-bold text-[var(--brand-primary)]">&larr; Manufacturing Control</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight">Pemeriksaan dan Pelepasan Hasil Produksi</h1>
        <p class="mt-2 max-w-3xl text-slate-500">Quality Control memeriksa output terhadap kriteria yang ditetapkan. Hanya kuantitas berstatus diterima yang dapat dipindahkan dari WIP menjadi barang jadi.</p>
        @if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif
        <div class="mt-8 space-y-4">
            @forelse($orders as $order)
                <x-ui.card>
                    <div class="flex flex-wrap justify-between gap-3"><div><strong>{{ $order->number }} — {{ $order->bom?->outputItem?->name }}</strong><p class="text-sm text-slate-500">Rencana {{ $order->planned_quantity }} · diterima QC {{ $order->inspections->where('result','accepted')->sum('inspected_quantity') }} · selesai {{ $order->completed_quantity }}</p></div><span class="rounded-lg bg-slate-100 px-3 py-1 text-xs font-bold">{{ strtoupper($order->status) }}</span></div>
                    @if(in_array($order->status, ['released', 'in_progress']) && auth()->user()->hasPermission('manufacturing.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
                        <form method="post" action="/admin/manufacturing/orders/{{ $order->id }}/inspect" class="mt-4 grid gap-3 rounded-xl bg-sky-50 p-4 md:grid-cols-3">@csrf
                            <h2 class="font-bold md:col-span-3">Catat Hasil Pemeriksaan QC</h2>
                            <input name="number" required placeholder="Nomor inspeksi" class="rounded-xl border p-3"><input type="number" step=".0001" name="inspected_quantity" required placeholder="Kuantitas diperiksa" class="rounded-xl border p-3">
                            <select name="result" class="rounded-xl border p-3"><option value="accepted">Diterima — boleh menjadi barang jadi</option><option value="rejected">Ditolak — tidak boleh diselesaikan</option></select>
                            <textarea name="criteria" required placeholder="Kriteria penerimaan: drawing, dimensi, welding, material, toleransi" class="rounded-xl border p-3 md:col-span-3"></textarea><textarea name="findings" placeholder="Temuan pemeriksaan" class="rounded-xl border p-3 md:col-span-2"></textarea><input name="evidence_reference" placeholder="Referensi foto/checksheet" class="rounded-xl border p-3">
                            <button class="rounded-xl bg-[var(--brand-primary)] p-3 font-bold text-white md:col-span-3">Simpan Keputusan Quality Control</button>
                        </form>
                    @endif
                    <div class="mt-4 overflow-x-auto"><table class="w-full text-sm"><thead><tr><th>Inspeksi</th><th>Kuantitas</th><th>Hasil</th><th>Kriteria</th><th>Evidence</th><th>Waktu</th></tr></thead><tbody>
                        @forelse($order->inspections as $inspection)<tr><td>{{ $inspection->number }}</td><td>{{ $inspection->inspected_quantity }}</td><td class="font-bold {{ $inspection->result === 'accepted' ? 'text-emerald-700' : 'text-red-700' }}">{{ $inspection->result === 'accepted' ? 'DITERIMA' : 'DITOLAK' }}</td><td>{{ $inspection->criteria }}</td><td>{{ $inspection->evidence_reference ?: '—' }}</td><td>{{ $inspection->inspected_at->format('d/m/Y H:i') }}</td></tr>@empty<tr><td colspan="6" class="p-6 text-center text-amber-700">Belum diperiksa QC. Production completion akan ditolak.</td></tr>@endforelse
                    </tbody></table></div>
                </x-ui.card>
            @empty<div class="rounded-2xl border border-dashed p-8 text-center">Belum ada production order untuk diperiksa.</div>@endforelse
        </div>
    </div>
</x-layouts.app>>
