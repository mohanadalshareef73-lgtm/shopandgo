<!doctype html>
<html lang="ar" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>قائمة الحجز · سلة #{{ $cart->id }}</title>
<link rel="stylesheet" href="{{ asset('assets/sg/sg.css') }}?v=6">
<style>body{background:#fff;padding:20px;max-width:900px;margin:0 auto;font-family:Somar}table{width:100%;border-collapse:collapse}td,th{border-bottom:1px solid #e5e7ee;padding:8px;text-align:right;vertical-align:middle}img{width:64px;height:64px;object-fit:cover;border-radius:8px}.ck{width:22px;height:22px}.q{font-family:InterNum;font-size:20px;font-weight:800;text-align:center;width:70px}tr.rep td{background:#fff4e6}tr.rep .q{color:#fff;background:#e8590c;border-radius:10px;font-size:24px}@media print{.np{display:none}}</style>
</head><body>
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
    <div><h2 style="margin:0">قائمة الحجز · {{ $cart->retailer_label }} #{{ $cart->id }}</h2><div class="note">{{ optional($cart->customer)->name }} · {{ optional($cart->customer)->code }} · {{ collect($rows)->sum('qty') }} قطعة في {{ count($rows) }} صنف · @php $nRep = collect($rows)->where('qty', '>', 1)->count(); @endphp @if ($nRep)<b style="color:#c2410c">🔁 {{ $nRep }} صنف مكرر — شوف خانة العدد</b>@else ما فيش تكرار @endif</div></div>
    <div class="np" style="display:flex;gap:6px"><button onclick="navigator.clipboard.writeText({{ json_encode($cart->source_url) }});this.textContent='✓ تنسخ'" class="btn ghost">⧉ نسخ رابط العميل</button><button onclick="print()" class="btn">🖨 طباعة / PDF</button></div>
</div>
<table><thead><tr><th>#</th><th></th><th>القطعة</th><th>المقاس / اللون</th><th>العدد</th><th>✓</th></tr></thead><tbody>
@foreach ($rows as $k => $r)
    <tr class="{{ $r['qty'] > 1 ? 'rep' : '' }}"><td class="num">{{ $k + 1 }}</td><td><img src="{{ route('carts.products.image', [$cart->id, $r['i']]) }}" onerror="this.style.visibility='hidden'"></td>
        <td>@if (!empty($r['url']))<a href="{{ $r['url'] }}" target="_blank">{{ $r['name'] }}</a>@else{{ $r['name'] }}@endif</td>
        <td>{{ $r['variant'] ?: '—' }}</td><td class="q">×{{ $r['qty'] }}</td><td><input type="checkbox" class="ck"></td></tr>
@endforeach
</tbody></table>
</body></html>
