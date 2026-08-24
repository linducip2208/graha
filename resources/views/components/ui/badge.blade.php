@php($tones = [
    'draft' => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    'pending_approval' => 'bg-amber-50 text-amber-700 ring-amber-600/25',
    'pending' => 'bg-amber-50 text-amber-700 ring-amber-600/25',
    'approved' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/25',
    'posted' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/25',
    'matched' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/25',
    'active' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/25',
    'operational' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/25',
    'open' => 'bg-sky-50 text-[var(--brand-primary)] ring-sky-600/25',
    'in_progress' => 'bg-sky-50 text-[var(--brand-primary)] ring-sky-600/25',
    'investigating' => 'bg-sky-50 text-[var(--brand-primary)] ring-sky-600/25',
    'revision_requested' => 'bg-violet-50 text-violet-700 ring-violet-600/25',
    'rejected' => 'bg-red-50 text-red-700 ring-red-600/25',
    'exception' => 'bg-red-50 text-red-700 ring-red-600/25',
    'maintenance' => 'bg-amber-50 text-amber-700 ring-amber-600/25',
    'closed' => 'bg-slate-100 text-slate-500 ring-slate-500/20',
    'cancelled' => 'bg-red-50 text-red-700 ring-red-600/25',
])
@php($tone = $tones[strtolower((string) $status)] ?? 'bg-slate-100 text-slate-600 ring-slate-500/20')
<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide ring-1 ring-inset $tone"]) }}>
<span class="h-1.5 w-1.5 rounded-full bg-current opacity-70"></span>{{ str($label ?? $status)->replace('_', ' ') }}
</span>
