@props(['id', 'title' => null, 'description' => null])
<div id="{{ $id }}" class="drawer-root" hidden data-drawer>
<div class="drawer-overlay" data-drawer-close></div>
<aside class="drawer-panel" role="dialog" aria-modal="true" aria-label="{{ $title }}">
<header class="flex shrink-0 items-start justify-between gap-3 border-b p-5" style="border-color:var(--border-subtle)">
<div class="min-w-0">
<h2 class="text-lg font-black tracking-tight">{{ $title }}</h2>
@if(filled($description))<p class="mt-0.5 text-sm text-[var(--text-muted)]">{{ $description }}</p>@endif
</div>
<button type="button" class="rounded-lg p-2 hover:bg-[var(--surface-muted)]" data-drawer-close aria-label="Tutup">✕</button>
</header>
<div class="drawer-body">{{ $slot }}</div>
</aside>
</div>
