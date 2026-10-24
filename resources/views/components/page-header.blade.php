@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    @if (trim($slot))<div class="flex flex-wrap gap-2">{{ $slot }}</div>@endif
</div>
