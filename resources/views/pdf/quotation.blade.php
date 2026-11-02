@php
    use App\Support\IndianNumber as N;
    use App\Models\Quotation;
    $rows = $quotation->items;
    $blank = max(0, 12 - $rows->count());
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 22px 26px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #111; }
    .navy { color: #2f3f73; }
    table { border-collapse: collapse; width: 100%; }
    .head td { vertical-align: top; padding: 0; }
    .biz { font-size: 26px; color: #2f3f73; letter-spacing: .5px; }
    .title { font-size: 30px; color: #7b8bc4; font-weight: bold; text-align: right; }
    .addr div { line-height: 15px; font-size: 10px; }
    .meta td { padding: 2px 4px; font-size: 10px; }
    .meta .box { border: 1px solid #333; min-width: 95px; width: 105px; }
    .bar { background: #34467c; color: #fff; font-weight: bold; padding: 3px 12px; font-size: 11px; }
    .items { margin-top: 10px; }
    .items th { background: #34467c; color: #fff; font-size: 10px; padding: 5px 4px; border: 1px solid #fff; text-align: center; }
    .items td { padding: 4px 5px; border-left: 1px solid #555; border-right: 1px solid #555; height: 13px; font-size: 9.5px; }
    .items tbody tr:nth-child(even) td { background: #efefef; }
    .items tfoot td { border: 1px solid #555; font-weight: bold; padding: 5px; font-size: 10px; }
    .r { text-align: right; } .c { text-align: center; }
    .bank { width: 58%; border: 1px solid #555; }
    .bank td { padding: 4px 12px; font-size: 9.5px; }
    .sign { text-align: center; font-size: 11.5px; vertical-align: top; padding-top: 18px; }
</style>
</head>
<body>
<table class="head">
    <tr>
        <td style="width:60%">
            <div class="biz">{{ $s['business_name'] }}</div>
            <div class="addr" style="margin-top:4px">
                <div>{{ $s['address_line1'] }}</div>
                <div>{{ $s['address_line2'] }}</div>
                <div>{{ $s['phone'] }}</div>
                <div style="color:#1a0dab;text-decoration:underline">{{ $s['email'] }}</div>
                <div>PAN NO :{{ $s['pan'] }}</div>
            </div>
        </td>
        <td style="width:40%">
            <div class="title">QUOTATION</div>
            <table class="meta" style="margin-top:10px; width:auto; margin-left:auto">
                <tr><td class="r">DATE</td><td class="box">{{ $quotation->quote_date->format('d-m-Y') }}</td></tr>
                <tr><td class="r">QUOTATION</td><td class="box">{{ $quotation->number }}</td></tr>
                @if ($quotation->valid_until)<tr><td class="r">VALID UNTIL</td><td class="box">{{ $quotation->valid_until->format('d-m-Y') }}</td></tr>@endif
            </table>
        </td>
    </tr>
</table>

<table style="margin-top:6px"><tr><td style="width:35%" class="bar">QUOTATION FOR</td><td></td></tr></table>
<div style="padding:6px 12px 0; min-height:50px; line-height:14px; font-size:10.5px">
    <strong>{{ $quotation->client->name }}</strong><br>
    @if ($quotation->client->address){!! nl2br(e($quotation->client->address)) !!}<br>@endif
</div>

<table class="items">
    <thead>
        <tr>
            <th style="width:8%">S.NO.</th>
            <th style="width:46%">CERTIFICATE</th>
            <th style="width:20%">TYPE</th>
            <th style="width:10%">SETS</th>
            <th style="width:16%">RATE (Rs.)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $i => $item)
            <tr>
                <td class="c">{{ $i + 1 }}</td>
                <td>{{ $item->certificate }}</td>
                <td class="c">{{ Quotation::typeLabel($item->ir_na) }}</td>
                <td class="c">{{ $item->sets }}</td>
                <td class="r">{{ N::money($item->rate) }}</td>
            </tr>
        @endforeach
        @for ($b = 0; $b < $blank; $b++)
            <tr><td></td><td></td><td></td><td></td><td></td></tr>
        @endfor
    </tbody>
    <tfoot><tr><td colspan="5" style="font-weight:normal">Rates are per certificate.</td></tr></tfoot>
</table>

@if ($quotation->notes)
    <div style="margin-top:12px; font-size:10px; line-height:14px"><strong>Notes:</strong><br>{!! nl2br(e($quotation->notes)) !!}</div>
@endif

<table style="margin-top:20px">
    <tr>
        <td style="width:58%"></td>
        <td class="sign">
            For. {{ $s['business_name'] }}
            <div style="height:38px"></div>
            Authorised signatory
        </td>
    </tr>
</table>
</body>
</html>
