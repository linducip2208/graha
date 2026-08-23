<x-layouts.app title="Notifikasi"><section class="mx-auto max-w-4xl px-4 py-8 lg:px-8">
<h1 class="text-2xl font-bold tracking-tight">Notifikasi</h1>
<p class="mt-2 text-slate-500">Pemberitahuan approval, pengingat SLA, dan aktivitas dokumen yang melibatkan Anda.</p>
@if(session('status'))<div class="mt-4 rounded-xl bg-emerald-50 p-4">{{ session('status') }}</div>@endif
<div class="mt-6 flex items-center justify-between">
<p class="text-sm text-slate-500">{{ auth()->user()->unreadNotifications->count() }} belum dibaca</p>
@if(auth()->user()->unreadNotifications->count() > 0)<form method="post" action="/admin/notifications/read-all">@csrf<button class="rounded-xl border px-4 py-2 text-sm font-semibold">Tandai semua dibaca</button></form>@endif
</div>
<div class="mt-4 space-y-3">
@forelse($notifications as $notification)
@php($data = $notification->data)
<article class="rounded-2xl border bg-white p-5 {{ $notification->read_at ? 'opacity-60' : 'border-l-4 border-l-sky-600' }}">
<div class="flex items-start justify-between gap-4">
<div>
<p class="font-bold">{{ ($data['document'] ?? 'Dokumen') }} — {{ $data['label'] ?? '' }}</p>
@php($eventText = match ($data['event'] ?? '') {
    'approval_requested' => 'Menunggu persetujuan Anda'.(! empty($data['step']) ? ' pada '.$data['step'] : '').'.',
    'approval_advanced' => 'Disetujui sebagian dan lanjut ke tahap berikutnya.',
    'approval_approved' => 'Telah disetujui penuh.',
    'approval_rejected' => 'Ditolak oleh approver.',
    'approval_revision_requested' => 'Perlu revisi dari Anda.',
    'approval_sla_overdue' => 'Melewati batas SLA — segera ditindaklanjuti.',
    default => 'Pembaruan dokumen.',
})
<p class="mt-1 text-sm text-slate-500">{{ $eventText }}
@if(!empty($data['comment']))<span class="block italic">"{{ $data['comment'] }}"</span>@endif
@if(!empty($data['due_at']))<span class="block">Batas SLA: {{ $data['due_at'] }}</span>@endif
</p>
<p class="mt-2 text-xs text-slate-400">{{ $notification->created_at->format('d/m/Y H:i') }}</p>
</div>
<div class="shrink-0 space-y-2 text-right">
@if($notification->read_at === null)<form method="post" action="/admin/notifications/{{ $notification->id }}/read">@csrf<button class="rounded-lg border px-3 py-1.5 text-xs font-semibold">Tandai dibaca</button></form>@endif
<a href="{{ $data['url'] ?? '/dashboard' }}" class="block rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-semibold text-white">Buka</a>
</div>
</div>
</article>
@empty
<p class="rounded-2xl border border-dashed bg-white p-8 text-center text-slate-500">Belum ada notifikasi. Notifikasi muncul saat ada dokumen menunggu persetujuan Anda atau SLA mendekati batas.</p>
@endforelse
</div>
<div class="mt-6">{{ $notifications->links() }}</div>
</section></x-layouts.app>
