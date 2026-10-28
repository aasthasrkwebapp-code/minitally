@props(['client'])
@php $alert = $client->dscAlert(); $days = $client->dscDaysLeft(); @endphp
@if ($alert === 'expired')
    <span {{ $attributes->merge(['class' => 'badge badge-unpaid']) }}>DSC expired</span>
@elseif ($alert === 'expiring')
    <span {{ $attributes->merge(['class' => 'badge badge-partial']) }}>{{ $days === 0 ? 'DSC ends today' : 'DSC ends in '.$days.' '.($days === 1 ? 'day' : 'days') }}</span>
@elseif ($alert === 'ok')
    <span {{ $attributes->merge(['class' => 'badge badge-paid']) }}>DSC {{ App\Models\Client::DSC_STATUSES[$client->dsc_status] ?? 'OK' }}</span>
@endif
