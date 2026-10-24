@props(['month', 'action'])
<form method="GET" action="{{ $action }}" class="flex items-center gap-2">
    {{ $slot }}
    <input type="month" name="month" value="{{ $month }}" class="input w-auto" data-autosubmit>
</form>
