<div class="card card-pad" data-row>
    <div class="mb-3 flex items-center justify-between">
        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Entry {{ is_int($i) ? $i + 1 : '__n__' }}</span>
        <button type="button" class="text-xs text-red-600 hover:underline" data-remove-row>Remove</button>
    </div>
    @include('entries._fields', [
        'name' => fn ($f) => "rows[$i][$f]",
        'val' => fn ($f) => $row[$f] ?? '',
    ])
</div>
