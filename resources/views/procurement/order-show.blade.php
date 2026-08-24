<x-layouts.app title="PO {{ $order->number }}"><section class="mx-auto max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
<x-ui.page-header title="PO {{ $order->number }} (v{{ $order->version }})" subtitle="{{ $order->vendor?->name }} · {{ $order->currency }} {{ number_format((float) $order->total, 2, ',', '.') }} · Tanggal {{ $order->order_date->format('d/m/Y') }}" status="{{ strtoupper($order->status) }}">
<a href="/admin/procurement" class="x-ui.button secondary">← Kembali ke Procurement</a>
</x-ui.page-header>

<x-ui.card label="Item Dipesan" class="mt-6">
<table class="w-full text-sm"><thead><tr><th>SKU</th><th>Item</th><th class="text-right">Qty</th><th class="text-right">Diterima</th><th class="text-right">Harga</th><th class="text-right">Subtotal</th></tr></thead><tbody>
@foreach($order->items as $line)
<tr class="border-t"><td class="font-mono text-xs">{{ $line->item?->sku }}</td><td>{{ $line->item?->name }}</td><td class="text-right font-mono">{{ $line->quantity }}</td><td class="text-right font-mono">{{ $line->received_quantity }}</td><td class="text-right font-mono">{{ number_format((float) $line->unit_price, 2, ',', '.') }}</td><td class="text-right font-mono">{{ number_format((float) ($line->quantity * $line->unit_price), 2, ',', '.') }}</td></tr>
@endforeach
</tbody></table>
</x-ui.card>

<x-ui.card label="Riwayat Revisi ({{ $order->revisions->count() }})" class="mt-5">
<div class="space-y-2">@forelse($order->revisions as $rev)
<div class="rounded-xl border p-3 text-sm"><strong>v{{ $rev->version }}</strong> — {{ \Illuminate\Support\Str::limit($rev->reason, 120) }} <span class="block text-[11px] text-slate-400">oleh {{ $rev->revised_by ? \App\Models\User::find($rev->revised_by)?->name : '-' }}</span></div>
@empty<p class="text-sm text-slate-400">Belum ada revisi.</p>@endforelse
</div>
</x-ui.card>
</section></x-layouts.app>
