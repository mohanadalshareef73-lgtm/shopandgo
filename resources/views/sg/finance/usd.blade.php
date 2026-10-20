@extends('sg.layout')
@section('title', 'المالية · شراء دولار')
@php
    use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n);
    $kinds = \App\Models\CurrencyExchange::KINDS;
    $dinarBal = collect($balances)->map(fn ($b) => $b['dinar']);
@endphp
@push('style')
<style>
.conv { display: grid; grid-template-columns: 1fr 30px 1fr; gap: 8px; align-items: end; }
.conv .op { text-align: center; font-size: 22px; color: var(--ink-3); padding-bottom: 12px; }
.rate { background: var(--brand); color: #fff; border-radius: 12px; padding: 10px 14px; margin: 12px 0; display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; }
.rate b { font-size: 26px; } .rate small { color: #c9cde0; font-size: 12.5px; }
.wal2 label input { position: absolute; opacity: 0; pointer-events: none; }
.wal2 { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-bottom: 10px; }
.wal2 label span { display: block; border: 1px solid var(--line); border-radius: 10px; padding: 8px 10px; cursor: pointer; font-size: 13px; }
.wal2 label span b { display: block; font-size: 14px; }
.wal2 label input:checked + span { border: 2px solid var(--brand); padding: 7px 9px; background: #f6f7fb; }
</style>
@endpush
@section('content')
@include('sg.finance._head')

<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">شرينا {{ $period->label }}</div><div class="v"><span class="num">{{ $f($buyUsd) }}</span> <small>$</small></div><div class="s">دفعنا {{ $f($buyLyd) }} د.ل · {{ $rows->where('to_currency', 'dollar')->count() }} مرات</div></div>
    <div class="card kpi"><div class="lbl">متوسط سعر الشراء</div><div class="v num">{{ $avg ? number_format($avg, 3) : '—' }}</div><div class="s">@if ($avg && $prevAvg)@include('sg.partials.chg', ['cur' => $avg, 'prev' => $prevAvg, 'invert' => true]) {{ $period->prevLabel }} {{ number_format($prevAvg, 2) }}@else آخر سعر {{ $rate ?: '—' }}@endif</div></div>
    <div class="card kpi"><div class="lbl">دولار لدينا الآن</div><div class="v"><span class="num">{{ $f($usdNow) }}</span> <small>$</small></div><div class="s">≈ {{ $f($usdNow * $rate) }} د.ل</div></div>
    <div class="card kpi"><div class="lbl">صرفنا على الرحلات</div><div class="v"><span class="num">{{ $f($spent) }}</span> <small>$</small></div><div class="s">{{ $period->label }}</div></div>
</div>

<div class="side-lay">
    <div>
        <div class="card mb">
            <h3>سعر الدولار الذي شرينا به <span class="h-note">آخر 90 يوم · كل عملية نقطة</span></h3>
            @if (count($chart['cur']) > 1)
                <svg class="chart" data-line data-h="220" data-chart='{{ json_encode($chart) }}'></svg>
            @else
                <div class="empty">يجب عمليتين على الأقل لكي يظهر الرسم</div>
            @endif
        </div>
        <div class="card tw">
            <div class="tw-h"><h3>سجل الشراء</h3>@foreach (['' => 'الكل'] + $kinds as $k => $v)<a class="pill {{ (string) request('kind') === (string) $k ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['kind' => $k ?: null]) }}">{{ $v }}</a>@endforeach</div>
            <div class="scroll-x">
            <table class="t"><thead><tr><th>التاريخ</th><th>النوع</th><th>من</th><th class="n">دفعنا</th><th class="n">استلمنا</th><th class="n">السعر</th><th>أين</th><th>سجّله</th><th></th></tr></thead><tbody>
            @forelse ($rows as $e)
                <tr>
                    <td class="note num">{{ $e->date->format('Y-m-d') }}</td>
                    <td><span class="chip b">{{ $kinds[$e->kind] ?? $e->kind }}</span></td>
                    <td class="note">{{ optional($e->fromPayment)->name }}</td>
                    <td class="n num minus">{{ $f($e->from_amount) }} {{ TS::sign($e->from_currency) }}</td>
                    <td class="n num plus">{{ $f($e->to_amount) }} {{ TS::sign($e->to_currency) }}</td>
                    <td class="n"><b class="num">{{ $e->rate }}</b></td>
                    <td class="note">{{ optional($e->toPayment)->name }}{{ $e->note ? ' · ' . $e->note : '' }}</td>
                    <td class="note">{{ optional($e->createdUser)->name }}</td>
                    <td>@can('exchange-delete')<form method="post" action="{{ route('finance.exchanges.destroy', $e) }}" data-confirm="حذف العملية وإرجاع الخزائن؟">@csrf @method('DELETE')<button class="more">×</button></form>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="9"><div class="empty">لا يوجد عمليات</div></td></tr>
            @endforelse
            </tbody></table>
            </div>
        </div>
    </div>

    @can('exchange-create')
    <form class="card sticky" method="post" action="{{ route('finance.exchanges.store') }}">
        @csrf
        <h3>شراء دولار جديد</h3>
        <label class="l">الدولار لأجل</label>
        <div class="opts fill mb" id="purp">
            @foreach (['visa' => '💳 فيزا', 'cash' => '💵 كاش', 'shein' => '🛍 بطاقات SHEIN', 'usdt' => '₮ USDT'] as $k => $v)
                <label><input type="radio" name="purpose" value="{{ $k }}" @checked(old('purpose', 'visa') === $k)><span>{{ $v }}</span></label>
            @endforeach
        </div>
        <div class="conv">
            <div><label class="l">دفعت (دينار)</label><input name="from_amount" id="fa" class="inp big" required inputmode="decimal" value="{{ old('from_amount') }}"></div>
            <div class="op">←</div>
            <div class="nsh"><label class="l">استلمت (دولار)</label><input name="to_amount" id="ta" class="inp big" required inputmode="decimal" value="{{ old('to_amount') }}"></div>
            <div class="ysh" hidden><label class="l">قيمة البطاقة ($)</label><input name="face" id="fv" class="inp big" inputmode="decimal" value="{{ old('face') }}"></div>
        </div>
        <div class="rate"><small>سعر الصرف</small><b class="num" id="rt">—</b><small id="rtn">{{ $avg ? 'متوسط ' . $period->label . ' ' . number_format($avg, 3) : '' }}</small></div>
        <input type="hidden" name="from_currency" value="dinar"><input type="hidden" name="to_currency" value="dollar">
        <label class="l">دفعت من</label>
        <div class="wal2">@foreach ($payments as $pm)<label><input type="radio" name="from_payment_id" value="{{ $pm->id }}" @checked($pm->id == $cashId)><span>{{ $pm->name }}<b class="num">{{ $f($dinarBal[$pm->id] ?? 0) }} د.ل</b></span></label>@endforeach</div>
        <div class="nsh">
        <label class="l">وصلت لـ</label>
        <div class="wal2" id="toW">@foreach ($dollarWallets as $w)<label><input type="radio" name="to_payment_id" value="{{ $w['id'] }}" @checked($visaId ? $w['id'] == $visaId : $loop->last)><span>{{ $w['name'] }}<b class="num">{{ $f($w['bal']) }} $</b></span></label>@endforeach</div>
        </div>
        <div class="ysh" hidden>
            <label class="l">البطاقات <span class="note">(رقم البطاقة {{ $cardDigits }} رقماً · الرمز {{ $pinDigits }} أرقام)</span></label>
            <div id="cardRows"></div>
            <button type="button" class="btn ghost sm" id="addCard" style="margin:4px 0 8px">+ بطاقة أخرى</button>
            <div class="note" id="cardSum"></div>
        </div>
        <div class="grid g2">
            <div><label class="l">التاريخ</label><input type="date" name="date" class="inp" value="{{ now()->toDateString() }}"></div>
            <div><label class="l">من أين؟ (اختياري)</label><input name="note" class="inp" placeholder="محل الصرافة…"></div>
        </div>
        <button class="btn block lg mt" id="ub">حفظ</button>
        <style>.crow{display:grid;grid-template-columns:1fr 92px 30px;gap:6px;margin-bottom:6px}.crow input{direction:ltr;text-align:center;font-family:InterNum;letter-spacing:.5px}.crow .x{border:0;background:none;color:var(--down);font-size:18px;cursor:pointer}.crow input.bad{border-color:var(--down)}</style>
    </form>
    @endcan
</div>
@endsection
@push('script')
<script>
(function () {
  const fa = document.getElementById('fa'), ta = document.getElementById('ta'); if (!fa) return;
  const upd = () => { const a = parseFloat(fa.value), b = parseFloat(ta.value); const r = a > 0 && b > 0 ? a / b : 0; document.getElementById('rt').textContent = r ? r.toFixed(3) : '—'; document.getElementById('ub').textContent = 'حفظ' + (b ? ' · ' + SG.fmt2(b) + ' $' : '') + (r ? ' بسعر ' + r.toFixed(3) : ''); };
  fa.addEventListener('input', upd); ta.addEventListener('input', upd);
  // الغرض: يحدد الخزينة المستلمة تلقائياً، و«بطاقات SHEIN» يفتح إدخال البطاقات
  const W = @json(['visa' => $visaId, 'cash' => $cashId]), CD = {{ $cardDigits }}, PD = {{ $pinDigits }};
  const rowsBox = document.getElementById('cardRows'), fv = document.getElementById('fv');
  let n = 0;
  function addRow(no = '', pin = '') { const i = n++; const d = document.createElement('div'); d.className = 'crow';
    d.innerHTML = `<input class="inp cno" name="cards[${i}][no]" maxlength="${CD}" inputmode="numeric" placeholder="${'0'.repeat(CD)}" value="${no}"><input class="inp cpin" name="cards[${i}][pin]" maxlength="${PD}" inputmode="numeric" placeholder="${'0'.repeat(PD)}" value="${pin}"><button type="button" class="x">×</button>`;
    d.querySelector('.x').onclick = () => { d.remove(); sum(); };
    d.querySelectorAll('input').forEach(x => x.addEventListener('input', () => { x.value = x.value.replace(/\D/g, ''); x.classList.toggle('bad', x.value !== '' && x.value.length !== (x.classList.contains('cno') ? CD : PD)); if (x.classList.contains('cno') && x.value.length === CD) d.querySelector('.cpin').focus(); sum(); }));
    rowsBox.appendChild(d); }
  function sum() { const k = [...rowsBox.querySelectorAll('.cno')].filter(x => x.value.length === CD).length, v = parseFloat(fv.value) || 0, a = parseFloat(fa.value) || 0;
    document.getElementById('cardSum').textContent = k ? k + ' بطاقة × ' + v + ' $ = ' + SG.fmt2(k * v) + ' $' + (a && k * v ? ' · سعر الدولار ' + (a / (k * v)).toFixed(3) : '') : '';
    if (cur() === 'shein') document.getElementById('ub').textContent = 'حفظ ' + (k ? k + ' بطاقة' : 'البطاقات'); }
  document.getElementById('addCard').onclick = () => addRow();
  fv.addEventListener('input', sum); fa.addEventListener('input', sum);
  const cur = () => (document.querySelector('#purp input:checked') || {}).value;
  function mode() { const p = cur(), sh = p === 'shein';
    document.querySelectorAll('.nsh').forEach(e => e.hidden = sh); document.querySelectorAll('.ysh').forEach(e => e.hidden = !sh);
    ta.required = !sh; fv.required = sh;
    if (sh && !rowsBox.children.length) addRow();
    if (!sh && W[p]) { const r = document.querySelector('#toW input[value="' + W[p] + '"]'); if (r) r.checked = true; }
    sh ? sum() : upd(); }
  document.querySelectorAll('#purp input').forEach(r => r.addEventListener('change', mode));
  mode();
})();
</script>
@endpush
