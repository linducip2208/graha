<x-layouts.app title="Output Produksi Ditolak">
    <section class="mx-auto max-w-7xl px-4 py-8 lg:px-8">
        <a href="/admin/manufacturing/quality" class="text-sm font-bold text-sky-700">&larr; Quality Control Produksi</a>
        <h1 class="mt-3 text-2xl font-bold tracking-tight">Output Ditolak dan Keputusan Disposition</h1>
        <p class="mt-2 max-w-3xl text-slate-500">Tentukan tindak lanjut setiap hasil produksi yang tidak memenuhi kriteria. Output ditolak tidak pernah menambah stok barang jadi: pilih <strong>rework</strong> untuk diperbaiki dan diperiksa ulang, atau <strong>scrap</strong> untuk membebankan nilainya dari WIP ke biaya scrap.</p>

        @if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4 text-emerald-800">{{ session('status') }}</div>@endif
        @if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>@endif

        @if(auth()->user()->hasPermission('finance.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
        <div class="mt-8 grid gap-3 rounded-2xl border bg-white p-5 md:grid-cols-2">
            <h2 class="font-bold md:col-span-2">Pemetaan Akun Biaya Scrap Produksi</h2>
            <p class="text-sm text-slate-500 md:col-span-2">Diperlukan sebelum disposition scrap dapat diposting. Sistem mendebit biaya scrap dan mengkredit Manufacturing WIP.</p>
            @foreach(['scrap_expense_debit' => 'Debit — Biaya Scrap Produksi', 'wip_credit' => 'Kredit — Manufacturing WIP'] as $side => $label)
                <form method="post" action="/admin/finance/mappings" class="grid gap-2 rounded-xl bg-slate-50 p-3">@csrf
                    <label class="text-sm font-semibold">{{ $label }}</label>
                    <input type="hidden" name="event_type" value="production_scrap"><input type="hidden" name="entry_side" value="{{ $side }}">
                    <select name="account_id" required class="w-full rounded-xl border p-3">
                        <option value="">Pilih akun</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" @selected(optional($mappings->firstWhere('entry_side', $side))->account_id === $account->id)>{{ $account->code }} — {{ $account->name }}</option>
                        @endforeach
                    </select>
                    <button class="rounded-xl bg-slate-900 p-3 font-bold text-white">Simpan {{ $label }}</button>
                </form>
            @endforeach
        </div>
        @else
        <div class="mt-8 rounded-2xl border bg-slate-50 p-5 text-sm text-slate-600"><strong>Pemetaan jurnal dikelola Finance.</strong> Anda tetap dapat melihat keputusan disposition, tetapi posting scrap memerlukan konfigurasi akun oleh pengguna dengan kewenangan Finance.</div>
        @endif

        <div class="mt-8 space-y-4">
            @forelse($inspections as $inspection)
                @php($disposed = $inspection->dispositions->sum('quantity'))
                @php($remaining = max(0, (float) $inspection->inspected_quantity - (float) $disposed))
                <article class="rounded-2xl border bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="font-bold">{{ $inspection->number }} — {{ $inspection->productionOrder->number }}</h2>
                            <p class="text-sm text-slate-500">Produk: {{ $inspection->productionOrder->bom?->outputItem?->name }} · Ditolak {{ $inspection->inspected_quantity }} · Belum diputuskan {{ number_format($remaining, 4, ',', '.') }}</p>
                            <p class="mt-2 text-sm"><strong>Temuan:</strong> {{ $inspection->findings ?: 'Tidak ada uraian temuan.' }}</p>
                        </div>
                        <span class="rounded-lg bg-red-100 px-3 py-1 text-xs font-bold text-red-700">DITOLAK QC</span>
                    </div>

                    @if($remaining > 0 && auth()->user()->hasPermission('manufacturing.manage', app(\App\Support\Tenancy\CurrentCompany::class)->id()))
                        <form method="post" action="/admin/manufacturing/inspections/{{ $inspection->id }}/dispose" class="mt-4 grid gap-3 rounded-xl bg-amber-50 p-4 md:grid-cols-3">
                            @csrf
                            <h3 class="font-bold md:col-span-3">Tetapkan Tindak Lanjut</h3>
                            <input name="number" required placeholder="Nomor disposition" class="rounded-xl border p-3">
                            <select name="disposition" required class="rounded-xl border p-3">
                                <option value="rework">Rework — perbaiki lalu inspeksi ulang</option>
                                <option value="scrap">Scrap — hapus dari WIP dan catat biaya</option>
                            </select>
                            <input type="number" step=".0001" min=".0001" max="{{ $remaining }}" name="quantity" required placeholder="Kuantitas" class="rounded-xl border p-3">
                            <textarea name="reason" required placeholder="Alasan keputusan berdasarkan temuan QC" class="rounded-xl border p-3 md:col-span-2"></textarea>
                            <textarea name="instruction" placeholder="Instruksi perbaikan, pemisahan, atau pemusnahan" class="rounded-xl border p-3"></textarea>
                            <input type="hidden" name="idempotency_key" value="disposition-{{ $inspection->id }}-{{ Str::uuid() }}">
                            <button class="rounded-xl bg-amber-700 p-3 font-bold text-white md:col-span-3">Simpan Keputusan dan Dampak Akuntansi</button>
                        </form>
                    @elseif($remaining <= 0)
                        <div class="mt-4 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">Seluruh kuantitas ditolak telah mempunyai keputusan disposition.</div>
                    @else
                        <div class="mt-4 rounded-xl bg-slate-50 p-4 text-sm text-slate-600">Anda memiliki akses lihat saja. Keputusan rework atau scrap memerlukan permission pengelolaan manufaktur.</div>
                    @endif

                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm"><thead><tr><th>Nomor</th><th>Keputusan</th><th>Kuantitas</th><th>Alasan</th><th>Jurnal</th><th>Waktu</th></tr></thead><tbody>
                        @forelse($inspection->dispositions as $disposition)
                            <tr><td>{{ $disposition->number }}</td><td class="font-bold">{{ $disposition->disposition === 'rework' ? 'REWORK' : 'SCRAP' }}</td><td>{{ $disposition->quantity }}</td><td>{{ $disposition->reason }}</td><td>{{ $disposition->journal_id ? '#'.$disposition->journal_id : 'Tidak ada — rework' }}</td><td>{{ $disposition->decided_at->format('d/m/Y H:i') }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="p-5 text-center text-amber-700">Belum ada keputusan. Output tetap tertahan dan tidak tersedia sebagai barang jadi.</td></tr>
                        @endforelse
                        </tbody></table>
                    </div>
                </article>
            @empty
                <div class="rounded-2xl border border-dashed p-8 text-center"><strong>Tidak ada output produksi yang ditolak.</strong><p class="mt-1 text-sm text-slate-500">Hasil QC berstatus ditolak akan muncul di sini untuk ditindaklanjuti.</p></div>
            @endforelse
        </div>
    </section>
</x-layouts.app>
