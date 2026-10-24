@foreach ($suggest as $field => $values)
<datalist id="dl-{{ $field }}">@foreach ($values as $v)<option value="{{ $v }}">@endforeach</datalist>
@endforeach
