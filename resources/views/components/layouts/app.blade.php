@php
    $nav = [
        ['dashboard', 'Dashboard', 'home', 'dashboard'],
        ['entries.index', 'Daily entries', 'list', 'entries.*'],
        ['billing.index', 'Month-end billing', 'send', 'billing.*'],
        ['invoices.index', 'Bills', 'bill', 'invoices.*'],
        ['bank.index', 'Bank statement', 'bank', 'bank.*'],
        ['payments.index', 'Payments', 'rupee', 'payments.*'],
        ['outstanding.index', 'Outstanding', 'bell', 'outstanding.*'],
        ['clients.index', 'Clients', 'users', 'clients.*'],
        ['settings.edit', 'Settings', 'cog', 'settings.*'],
    ];
    $bottom = [
        ['dashboard', 'Home', 'home', 'dashboard'],
        ['entries.index', 'Entries', 'list', 'entries.*'],
        ['billing.index', 'Billing', 'send', 'billing.*'],
        ['bank.index', 'Bank', 'bank', 'bank.*'],
        ['outstanding.index', 'Dues', 'bell', 'outstanding.*'],
    ];
    $business = \App\Models\Setting::get('business_name');
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#34467c">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="MiniTally">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" href="/icons/icon-192.png">
    <title>{{ isset($title) ? $title.' · ' : '' }}MiniTally</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased">
<div class="min-h-full lg:pl-64">
    {{-- Sidebar: laptop and desktop --}}
    <aside class="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col bg-brand-800 lg:flex">
        @include('layouts.partials.sidebar')
    </aside>

    {{-- Slide-in menu: mobile and tablet --}}
    <div id="mobile-menu" class="fixed inset-0 z-40 hidden lg:hidden">
        <div class="absolute inset-0 bg-slate-900/50" data-toggle="mobile-menu"></div>
        <aside class="relative flex h-full w-72 max-w-[85%] flex-col bg-brand-800 shadow-xl">
            <button type="button" class="absolute right-3 top-4 rounded p-1 text-brand-100 hover:bg-white/10" data-toggle="mobile-menu" aria-label="Close menu"><x-icon name="x" /></button>
            @include('layouts.partials.sidebar')
        </aside>
    </div>

    {{-- Top bar: mobile and tablet --}}
    <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur lg:hidden">
        <button type="button" class="-ml-1 rounded p-1.5 text-slate-600 hover:bg-slate-100" data-toggle="mobile-menu" aria-label="Open menu"><x-icon name="menu" class="size-6" /></button>
        <div class="min-w-0 flex-1 truncate font-semibold text-brand-700">{{ $business }}</div>
        <a href="{{ route('entries.create') }}" class="btn btn-primary btn-sm"><x-icon name="plus" class="size-4" />Entry</a>
    </header>

    <main class="mx-auto max-w-7xl px-4 pb-28 pt-5 sm:px-6 lg:px-8 lg:pb-10 lg:pt-8">
        @if (session('ok'))
            <div class="mb-4 flex items-start gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><x-icon name="check" class="mt-0.5 size-4 shrink-0" /><div>{{ session('ok') }}</div></div>
        @endif
        @if (session('errors_list'))
            <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <div class="font-medium">Some could not be sent:</div>
                <ul class="mt-1 list-disc pl-5">@foreach (session('errors_list') as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <ul class="list-disc pl-5">@foreach ($errors->all() as $err)<li>{{ $err }}</li>@endforeach</ul>
            </div>
        @endif

        {{ $slot }}
    </main>

    {{-- Bottom navigation: phones --}}
    <nav class="fixed inset-x-0 bottom-0 z-20 grid grid-cols-5 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
        @foreach ($bottom as [$route, $label, $icon, $pattern])
            <a href="{{ route($route) }}" class="flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium {{ request()->routeIs($pattern) ? 'text-brand-600' : 'text-slate-500' }}">
                <x-icon :name="$icon" class="size-6" />{{ $label }}
            </a>
        @endforeach
    </nav>
</div>
</body>
</html>
