@props(['label', 'value', 'hint' => null, 'tone' => 'slate', 'href' => null])
@php $tones = ['slate' => 'text-slate-900', 'red' => 'text-red-600', 'green' => 'text-emerald-600', 'brand' => 'text-brand-600', 'amber' => 'text-amber-600']; @endphp
@if ($href)<a href="{{ $href }}" class="card card-pad block hover:border-brand-200 hover:shadow">@else<div class="card card-pad">@endif
    <div class="text-xs font-medium uppercase tracking-wide text-slate-500">{{ $label }}</div>
    <div class="mt-1 text-xl font-semibold sm:text-2xl {{ $tones[$tone] }}">{{ $value }}</div>
    @if ($hint)<div class="mt-0.5 text-xs text-slate-500">{{ $hint }}</div>@endif
@if ($href)</a>@else</div>@endif
