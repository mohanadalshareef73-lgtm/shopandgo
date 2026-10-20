<html dir="rtl"><head><meta charset="utf-8">
<style>
body { font-family: somar; font-size: 8pt; color: #1f2433; }
.hd { border-bottom: 0.6mm solid #1c45a3; padding-bottom: 2mm; margin-bottom: 3mm; }
.hd b { font-size: 13pt; color: #1c45a3; }
table.t { width: 100%; border-collapse: collapse; }
table.t th { background: #1c45a3; color: #fff; padding: 1.5mm 1mm; font-size: 7.5pt; }
table.t td { border: 0.3mm solid #d5d9e4; padding: 1.5mm 1mm; text-align: center; vertical-align: top; }
table.t td.r { text-align: right; }
table.t tr.tot td { background: #fff4e5; font-weight: bold; }
.dep { color: #b5480f; font-weight: bold; }
</style></head><body>
@php $who = $rows->map(fn ($d) => $d->courier_name ?: optional($d->courier)->name)->filter()->unique()->implode('، '); @endphp
<div class="hd"><b>Shop & Go</b> &nbsp;·&nbsp; كشف {{ $title }} @if ($who) &nbsp;·&nbsp; المندوب: <b>{{ $who }}</b>@endif &nbsp;·&nbsp; {{ now()->format('Y-m-d g:i a') }} &nbsp;·&nbsp; {{ $rows->count() }} زبون</div>
<table class="t">
    <tr><th>#</th><th>الكود</th><th>الزبون</th><th>الهاتف</th><th>العنوان</th><th>النوع</th><th>الإيصالات</th><th>التخزين</th><th>طرود</th><th>الباقي مع الشحن</th><th>التوصيل</th><th>المطلوب</th><th>توقيع</th></tr>
    @foreach ($rows as $i => $d)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td><b>{{ optional($d->customer)->code }}</b></td>
            <td class="r">{{ $d->recipient_name ?: optional($d->customer)->name }}</td>
            <td>{{ $d->recipient_phone }}@if ($d->recipient_phone2)<br>{{ $d->recipient_phone2 }}@endif</td>
            <td class="r" style="width:30mm">{{ $d->address ?: '—' }}</td>
            <td class="{{ $d->kind === 'deposit' ? 'dep' : '' }}">{{ $d->kind === 'deposit' ? 'عربون' : 'طلبية' }}</td>
            <td>{{ $d->orders->pluck('code')->implode('، ') }}</td>
            <td>{{ $d->orders->pluck('storage_num')->filter()->unique()->implode('، ') ?: '—' }}</td>
            <td>{{ $d->parcels }}</td>
            <td>{{ $f($d->amount) }}</td>
            <td>{{ $f($d->fee) }}</td>
            <td><b>{{ $f($d->amount + ($d->fee_payer === 'company' ? 0 : $d->fee)) }}</b></td>
            <td style="width:14mm"></td>
        </tr>
    @endforeach
    <tr class="tot"><td colspan="8">الإجمالي</td><td>{{ $rows->sum('parcels') }}</td><td>{{ $f($rows->sum('amount')) }}</td><td>{{ $f($rows->sum('fee')) }}</td><td>{{ $f($rows->sum(fn ($d) => $d->amount + ($d->fee_payer === 'company' ? 0 : $d->fee))) }}</td><td></td></tr>
</table>
</body></html>
