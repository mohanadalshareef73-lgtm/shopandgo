@php $ph = fn ($p) => (str_contains((string) $p, '@') || strlen(preg_replace('/\D/', '', (string) $p)) > 13) ? '' : $p; @endphp
<html dir="rtl"><head><meta charset="utf-8">
<style>
body { font-family: somar; font-size: 16pt; color: #000; font-weight: bold; }
.hd { border-bottom: 1.2mm solid #000; padding-bottom: 3mm; margin-bottom: 4mm; font-size: 15pt; }
.hd b { font-size: 22pt; color: #000; }
table.t { width: 100%; border-collapse: collapse; }
table.t th { background: #000; color: #fff; padding: 2.5mm 1.5mm; font-size: 14pt; font-weight: bold; }
table.t td { border: 0.8mm solid #000; padding: 3mm 1.5mm; font-size: 16pt; font-weight: bold; color: #000; text-align: center; vertical-align: middle; }
table.t td.r { text-align: right; }
table.t td .big { font-size: 18pt; }
table.t td .sm { font-size: 13pt; }
table.t tr.tot td { background: #e6e6e6; font-weight: bold; font-size: 17pt; }
.dep { color: #000; text-decoration: underline; }
</style></head><body>
@php $who = $rows->map(fn ($d) => $d->courier_name ?: optional($d->courier)->name)->filter()->unique()->implode('، '); @endphp
<div class="hd"><b>Shop & Go</b> &nbsp;·&nbsp; كشف {{ $title }} @if ($who) &nbsp;·&nbsp; المندوب: <b>{{ $who }}</b>@endif &nbsp;·&nbsp; {{ now()->format('Y-m-d g:i a') }} &nbsp;·&nbsp; {{ $rows->count() }} زبون</div>
<table class="t">
    <tr><th style="width:8mm">#</th><th>الزبون</th><th>الهاتف</th><th>العنوان</th><th>الرف</th><th>الأكياس</th><th>الباقي</th></tr>
    @foreach ($rows as $i => $d)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td class="r"><span class="big">{{ optional($d->customer)->code }}</span><br>{{ $d->recipient_name ?: optional($d->customer)->name }}@if ($d->kind === 'deposit')<br><span class="dep sm">عربون</span>@endif</td>
            <td><span class="big">{{ $ph($d->recipient_phone) }}</span>@if ($ph($d->recipient_phone2))<br>{{ $ph($d->recipient_phone2) }}@endif</td>
            <td class="r">{{ $d->address ?: '—' }}</td>
            <td><span class="big">{{ $d->orders->pluck('storage_num')->filter()->unique()->implode('، ') ?: '—' }}</span></td>
            <td><span class="big">{{ $d->parcels }}</span></td>
            <td><span class="big">{{ $f($d->amount) }}</span></td>
        </tr>
    @endforeach
    <tr class="tot"><td colspan="5">الإجمالي · {{ $rows->count() }} زبون</td><td>{{ $rows->sum('parcels') }}</td><td>{{ $f($rows->sum('amount')) }}</td></tr>
</table>
</body></html>
