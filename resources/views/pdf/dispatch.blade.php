@php $ph = fn ($p) => (str_contains((string) $p, '@') || strlen(preg_replace('/\D/', '', (string) $p)) > 13) ? '' : $p; @endphp
<html dir="rtl"><head><meta charset="utf-8">
<style>
body { font-family: somar; font-size: 16pt; color: #000; font-weight: bold; }
.hd { border-bottom: 1mm solid #000; padding-bottom: 3mm; margin-bottom: 4mm; font-size: 15pt; }
.hd b { font-size: 22pt; color: #000; }
table.t { width: 100%; border-collapse: collapse; }
table.t th { background: #000; color: #fff; padding: 3mm 1.5mm; font-size: 14pt; }
table.t td { border: 0.5mm solid #000; padding: 4mm 1.5mm; font-size: 16pt; font-weight: bold; color: #000; text-align: center; vertical-align: top; }
table.t td.r { text-align: right; }
table.t tr.tot td { background: #fff4e5; font-weight: bold; }
.dep { color: #b5480f; font-weight: bold; }
</style></head><body>
@php $who = $rows->map(fn ($d) => $d->courier_name ?: optional($d->courier)->name)->filter()->unique()->implode('، '); @endphp
<div class="hd"><b>Shop & Go</b> &nbsp;·&nbsp; كشف {{ $title }} @if ($who) &nbsp;·&nbsp; المندوب: <b>{{ $who }}</b>@endif &nbsp;·&nbsp; {{ now()->format('Y-m-d g:i a') }} &nbsp;·&nbsp; {{ $rows->count() }} زبون</div>
<table class="t">
    <tr><th>#</th><th>الكود</th><th>الزبون</th><th>الهاتف</th><th>العنوان</th><th>النوع</th><th>التخزين</th><th>طرود</th><th>الباقي مع الشحن</th></tr>
    @foreach ($rows as $i => $d)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td><b>{{ optional($d->customer)->code }}</b></td>
            <td class="r">{{ $d->recipient_name ?: optional($d->customer)->name }}</td>
            <td>{{ $ph($d->recipient_phone) }}@if ($d->recipient_phone2)<br>{{ $ph($d->recipient_phone2) }}@endif</td>
            <td class="r" style="width:34mm">{{ $d->address ?: '—' }}</td>
            <td class="{{ $d->kind === 'deposit' ? 'dep' : '' }}">{{ $d->kind === 'deposit' ? 'عربون' : 'طلبية' }}</td>
            <td>{{ $d->orders->pluck('storage_num')->filter()->unique()->implode('، ') ?: '—' }}</td>
            <td>{{ $d->parcels }}</td>
            <td><b>{{ $f($d->amount) }}</b></td>
        </tr>
    @endforeach
    <tr class="tot"><td colspan="7">الإجمالي</td><td>{{ $rows->sum('parcels') }}</td><td>{{ $f($rows->sum('amount')) }}</td></tr>
</table>
</body></html>
