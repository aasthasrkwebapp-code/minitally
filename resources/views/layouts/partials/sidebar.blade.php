<div class="flex h-16 shrink-0 items-center gap-3 px-5">
    <img src="/icons/icon-192.png" alt="" class="size-9 rounded-lg">
    <div class="min-w-0">
        <div class="truncate text-sm font-semibold text-white">{{ $business }}</div>
        <div class="text-xs text-brand-200">Billing</div>
    </div>
</div>
<nav class="flex-1 space-y-1 overflow-y-auto px-3 py-3">
    @foreach ($nav as [$route, $label, $icon, $pattern])
        <a href="{{ route($route) }}" class="nav-link {{ request()->routeIs($pattern) ? 'active' : '' }}"><x-icon :name="$icon" />{{ $label }}</a>
    @endforeach
</nav>
<div class="space-y-1 border-t border-white/10 p-3">
    <button type="button" data-install class="nav-link hidden w-full"><x-icon name="phone" />Install app</button>
    <form method="POST" action="{{ route('logout') }}">@csrf
        <button class="nav-link w-full"><x-icon name="logout" />Log out ({{ auth()->user()->name }})</button>
    </form>
</div>
