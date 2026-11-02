@props(['title', 'subtitle' => null])
<div class="mb-5 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
    <div class="min-w-0">
        <h1 class="text-xl font-semibold text-slate-900 sm:text-2xl">{{ $title }}</h1>
        @if ($subtitle)<p class="mt-0.5 text-sm text-slate-500">{{ $subtitle }}</p>@endif
    </div>
    @if (trim($slot))<div class="flex flex-wrap gap-2 lg:shrink-0 lg:justify-end">{{ $slot }}</div>@endif
</div>
