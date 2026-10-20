@extends('sg.layout')
@section('title', 'التقارير · ' . $info[0])
@php
    use App\Services\TreasuryService as TS; use App\Http\Controllers\Sg\ReportsController as RC;
    $f = fn ($n) => TS::fmt($n);
    $q = fn ($extra = []) => request()->fullUrlWithQuery($extra);
@endphp
@push('style')
<style>
.rlist { padding: 8px; }
.rlist .grp { font-size: 12px; color: var(--ink-3); margin: 12px 10px 6px; }
.rlist a { display: flex; gap: 10px; align-items: center; padding: 9px 10px; border-radius: 12px; }
.rlist a.on { background: var(--line-soft); }
.rlist a small { display: block; font-size: 12px; color: var(--ink-3); }
.rlist .ib { width: 34px; height: 34px; border-radius: 10px; display: grid; place-items: center; flex: none; font-size: 16px; }
.paper { padding: 0; overflow: hidden; }
.paper .ph { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--line-soft); flex-wrap: wrap; }
.paper .ph h2 { margin: 0; font-size: 17px; }
.doc { padding: 22px 26px; }
.doc-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid var(--ink); padding-bottom: 14px; margin-bottom: 16px; }
.doc-head img { width: 120px; }
.doc-head .t { font-size: 22px; font-weight: 800; }
.doc-head .m { font-size: 13px; color: var(--ink-3); margin-top: 4px; }
.k4 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 10px; margin-bottom: 18px; }
.k4 div { border: 1px solid var(--line); border-radius: 12px; padding: 10px 12px; }
.k4 small { display: block; font-size: 12px; color: var(--ink-3); }
.k4 b { font-size: 20px; }
.h5 { font-size: 15px; font-weight: 800; margin: 18px 0 8px; display: flex; align-items: center; gap: 8px; }
.h5::before { content: ""; width: 4px; height: 16px; background: var(--brand); border-radius: 2px; }
.pl { width: 100%; border-collapse: collapse; }
.pl td { padding: 8px 6px; font-size: 14px; border-bottom: 1px dashed var(--line); }
.pl td.n { text-align: left; font-weight: 700; }
.pl tr.tot td { border-top: 2px solid var(--ink); border-bottom: 0; font-size: 16px; font-weight: 800; }
.two { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
.explain-box { background: #f6f8fb; border-radius: 12px; padding: 12px 14px; font-size: 13.5px; color: var(--ink-2); line-height: 1.9; }
.explain-box b { color: var(--ink); }
.sched { display: flex; align-items: center; gap: 10px; background: #e7f8ee; border: 1px solid #c6ecd4; border-radius: 12px; padding: 10px 14px; font-size: 13.5px; color: #145c33; margin-top: 10px; }
@media (max-width: 900px) { .two, .k4 { grid-template-columns: 1fr 1fr; } }
@media print { .rlist, .ph, .sg-burger, .top { display: none !important; } .side-lay { display: block; } .paper { border: 0; } }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>التقارير</h1><div class="sub">اختر التقرير والفترة، وتطلعه PDF أو Excel أو تبعثه واتساب</div></div>
    <div class="acts">
        @include('sg.partials.period')
        <form method="get" style="display:flex;gap:6px;align-items:center"><input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="p" value="custom"><input type="date" name="from" class="inp" style="height:40px;width:150px" value="{{ $period->from->toDateString() }}"><span class="note">لـ</span><input type="date" name="to" class="inp" style="height:40px;width:150px" value="{{ $period->to->toDateString() }}"><button class="btn ghost sm">فترة</button></form>
        <button class="sg-burger">☰</button>
    </div>
</div>
<div class="side-lay l">
    <div class="card rlist">
        @foreach (RC::LIST as $g => $items)
            <div class="grp">{{ $g }}</div>
            @foreach ($items as $k => $it)
                @continue(in_array($k, RC::PROFIT_ONLY, true) && !auth()->user()->can('profit-view'))
                <a href="{{ route('sg.reports', ['type' => $k, 'p' => $k === 'daily' ? 'day' : request('p', 'month')]) }}" class="{{ $type === $k ? 'on' : '' }}"><span class="ib" style="background:{{ $it[2] }}14;color:{{ $it[2] }}">{{ $it[3] }}</span><div><b>{{ $it[0] }}</b>@if ($it[1])<small>{{ $it[1] }}</small>@endif</div></a>
            @endforeach
        @endforeach
    </div>

    <div class="card paper">
        <div class="ph">
            <h2>{{ $info[0] }}</h2><span class="chip n">{{ $period->label }}</span>
            <span style="margin-inline-start:auto;display:flex;gap:8px;flex-wrap:wrap">
                <button class="btn ghost sm" onclick="window.print()">🖨 طباعة / PDF</button>
                <a class="btn ghost sm" href="{{ $q(['export' => 'csv']) }}">⬇ Excel</a>
                @if (in_array($type, ['summary', 'daily']))<form method="post" action="{{ route('sg.reports.whatsapp') }}">@csrf<input type="hidden" name="p" value="{{ $period->preset }}"><input type="hidden" name="from" value="{{ $period->from->toDateString() }}"><input type="hidden" name="to" value="{{ $period->to->toDateString() }}"><button class="btn green sm">💬 ابعثه واتساب</button></form>@endif
            </span>
        </div>
        <div class="doc">
            <div class="doc-head">
                <div><div class="t">{{ $info[0] }} — {{ $period->label }}</div><div class="m">من {{ $period->from->format('Y-m-d') }} لـ {{ $period->to->format('Y-m-d') }} · طلع {{ now()->format('Y-m-d g:i A') }} · بواسطة {{ auth()->user()->name }}{{ !empty($table['subtitle']) ? ' · ' . $table['subtitle'] : '' }}</div></div>
                <img src="{{ asset('assets/sg/logo.png') }}" alt="">
            </div>

            @if (isset($summary))
                @php $s = $summary; $r = $s['r']; @endphp
                <div class="k4">
                    <div><small>الطلبات</small><b class="num">{{ number_format($s['orders']) }}</b> @include('sg.partials.chg', ['cur' => $s['orders'], 'prev' => $s['ordersPrev']])</div>
                    <div><small>قيمة الإيصالات</small><b class="num">{{ $f($s['ordersValue']) }}</b></div>
                    @can('profit-view')<div><small>صافي الربح</small><b class="num plus">{{ $f($s['net']) }}</b> @include('sg.partials.chg', ['cur' => $s['net'], 'prev' => $s['netPrev']])</div>@endcan
                    <div><small>الديون عند العملاء</small><b class="num minus">{{ $f($s['receivables']) }}</b></div>
                </div>
                @if (isset($daily))
                    <div class="h5">الخزينة {{ $period->label }}</div>
                    <table class="pl">
                        <tr><td>دخل الكاش</td><td class="n num plus">+{{ $f($daily['cashIn']) }}</td></tr>
                        <tr><td>طلع من الكاش</td><td class="n num minus">−{{ $f($daily['cashOut']) }}</td></tr>
                        <tr><td>دخل المصرف</td><td class="n num plus">+{{ $f($daily['bankIn']) }}</td></tr>
                        <tr><td>طلع من المصرف</td><td class="n num minus">−{{ $f($daily['bankOut']) }}</td></tr>
                        @foreach ($daily['shifts'] as $sh)<tr><td>تقفيل {{ optional($sh->user)->name }}</td><td class="n num">{{ $f($sh->counted_cash) }} {{ abs($sh->diff) >= 0.01 ? '(فرق ' . $f($sh->diff) . ')' : '✓' }}</td></tr>@endforeach
                    </table>
                @endif
                @can('profit-view')
                <div class="two">
                    <div>
                        <div class="h5">حساب الربح</div>
                        <table class="pl">
                            <tr><td>مبيعات الرحلات (الذي قلناه للعملاء)</td><td class="n num">{{ $f($r['sales']['dinar']) }}</td></tr>
                            <tr><td>− تكلفة الشراء ({{ $f($s['usdSpent']) }} $ {{ $s['usdAvg'] ? 'بمتوسط ' . number_format($s['usdAvg'], 2) : '' }})</td><td class="n num minus">{{ $f($r['cost']['dinar']) }}</td></tr>
                            <tr><td>= ربح الرحلات</td><td class="n num plus">{{ $f($r['goodsProfit']['dinar']) }}</td></tr>
                            <tr><td>+ ربح الشحن (قبضنا {{ $f($r['shipIncome']['dinar']) }} − دفعنا {{ $f($r['shipCost']['dinar']) }})</td><td class="n num">{{ $f($r['shipIncome']['dinar'] - $r['shipCost']['dinar']) }}</td></tr>
                            <tr><td>− مصاريف الشركة</td><td class="n num minus">{{ $f($r['expenses']['dinar'] + $r['paymentExpenses']['dinar']) }}</td></tr>
                            <tr class="tot"><td>صافي الربح</td><td class="n num">{{ $f($s['net']) }} د.ل</td></tr>
                        </table>
                    </div>
                    <div>
                        <div class="h5">حركة المبلغ</div>
                        <table class="pl">
                            <tr><td>قبضنا من العملاء (عربون + باقي)</td><td class="n num plus">+{{ $f($s['collected']) }}</td></tr>
                            <tr><td>شرينا دولار ({{ $f($s['usdGot']) }} $)</td><td class="n num minus">−{{ $f($s['usdPaid']) }}</td></tr>
                            <tr><td>دفعنا لشركة الشحن</td><td class="n num minus">−{{ $f($r['shipCost']['dinar']) }}</td></tr>
                            <tr><td>مصاريف</td><td class="n num minus">−{{ $f($r['expenses']['dinar']) }}</td></tr>
                            <tr><td>سحوبات الشركاء</td><td class="n num minus">−{{ $f($s['partners']) }}</td></tr>
                            <tr class="tot"><td>الفرق في الخزائن</td><td class="n num">{{ $s['moneyDiff'] >= 0 ? '+' : '' }}{{ $f($s['moneyDiff']) }} د.ل</td></tr>
                        </table>
                    </div>
                </div>
                @endcan
                <div class="two">
                    <div>
                        <div class="h5">النشاط</div>
                        <table class="pl">
                            <tr><td>عملاء جدد</td><td class="n num">{{ number_format($s['newCustomers']) }}</td></tr>
                            <tr><td>رحلات شراء</td><td class="n num">{{ $s['trips'] }}</td></tr>
                            <tr><td>بضاعة وصلت (إيصال)</td><td class="n num">{{ $s['arrived'] }}</td></tr>
                            <tr><td>طلبيات تسلّمت</td><td class="n num">{{ $s['delivered'] }}</td></tr>
                            <tr><td>متوسط البقاء في الرف</td><td class="n num">{{ $s['avgShelf'] !== null ? $s['avgShelf'] . ' يوم' : '—' }}</td></tr>
                        </table>
                    </div>
                    <div>
                        <div class="h5">الأحسن</div>
                        <table class="pl">
                            @can('profit-view')<tr><td>أربح رحلة</td><td class="n">@if ($s['bestTrip'])<span class="code">{{ $s['bestTrip'][0] }}</span> · <span class="num">{{ $f($s['bestTrip'][1]) }}</span>@else — @endif</td></tr>@endcan
                            <tr><td>أكثر منصة</td><td class="n">{{ $s['topPlatform'] ? $s['topPlatform'][0] . ' · ' . $s['topPlatform'][1] . '%' : '—' }}</td></tr>
                            <tr><td>أكثر موظف شغل</td><td class="n">{{ $s['topUser'] ? $s['topUser'][0] . ' · ' . $s['topUser'][1] . ' عملية' : '—' }}</td></tr>
                            <tr><td>أكبر عميل</td><td class="n">{{ $s['topCustomer'] ? $s['topCustomer'][0] . ' · ' . $f($s['topCustomer'][1]) : '—' }}</td></tr>
                        </table>
                    </div>
                </div>
                @can('profit-view')
                <div class="h5">ماذا يعني التقرير؟</div>
                <div class="explain-box">{!! implode('<br>', $s['explain']) !!}</div>
                @endcan
                <div class="sched">⏰ {{ $monthly ? 'التقرير الشهري يتبعث أوتوماتيك أول يوم في كل شهر' . ($phones ? ' لـ ' . $phones : '') : 'الإرسال الشهري الأوتوماتيكي مقفول' }} · <a href="{{ route('sg.settings') }}" style="color:#145c33;font-weight:700">تعديل</a></div>
            @else
                @if (($table['filters'] ?? null) === 'profit')
                    @php $srcSel = (array) request('src', array_keys(RC::PROFIT_SOURCES)); @endphp
                    <form method="get" class="mb" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                        <input type="hidden" name="type" value="profit_detail"><input type="hidden" name="p" value="{{ $period->preset }}">
                        @if (request('from'))<input type="hidden" name="from" value="{{ request('from') }}"><input type="hidden" name="to" value="{{ request('to') }}">@endif
                        @foreach (RC::PROFIT_SOURCES as $k => $v)<label class="ck" style="margin:0"><input type="checkbox" name="src[]" value="{{ $k }}" @checked(in_array($k, $srcSel, true)) data-autosubmit> {{ $v }}</label>@endforeach
                        <select name="pf" class="inp" style="width:auto;height:38px" data-autosubmit><option value="">كل المنصات</option>@foreach (\App\Models\Platform::visible()->orderBy('name')->get() as $pl)<option value="{{ $pl->id }}" @selected(request('pf') == $pl->id)>{{ $pl->name }}</option>@endforeach</select>
                        <input name="q" class="inp" style="width:200px;height:38px" value="{{ request('q') }}" placeholder="بحث: رحلة، إيصال، عميل…">
                        <button class="btn sm">تطبيق</button>
                    </form>
                    @php $srcTot = collect($table['rows'])->groupBy(1); @endphp
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px">@foreach ($srcTot as $k => $g)<div class="hint" style="margin:0;padding:8px 12px"><small class="note">{{ $k }}</small><div><b class="num {{ $g->sum(6) >= 0 ? 'plus' : 'minus' }}">{{ $f($g->sum(6)) }}</b> <small>د.ل</small></div></div>@endforeach</div>
                @endif
                @if ($type === 'statement')
                    <form method="get" class="mb" style="display:flex;gap:8px"><input type="hidden" name="type" value="statement"><input type="hidden" name="p" value="{{ $period->preset }}"><select name="customer_id" class="inp" data-autosubmit><option value="">اختر العميل…</option>@foreach ($customers as $cu)<option value="{{ $cu->id }}" @selected(request('customer_id') == $cu->id)>{{ $cu->name }} ({{ $cu->code }})</option>@endforeach</select></form>
                @endif
                <div class="scroll-x">
                <table class="t">
                    <thead><tr>@foreach ($table['head'] as $i => $h)<th class="{{ in_array($i, $table['num'] ?? []) ? 'n' : '' }}">{{ $h }}</th>@endforeach</tr></thead>
                    <tbody>
                    @forelse ($table['rows'] as $row)
                        <tr>@foreach (array_values($row) as $i => $v)<td class="{{ in_array($i, $table['num'] ?? []) ? 'n num' : '' }} wrap">{{ is_numeric($v) && in_array($i, $table['num'] ?? []) ? $f($v) : $v }}</td>@endforeach</tr>
                    @empty
                        <tr><td colspan="{{ count($table['head']) }}"><div class="empty">لا يوجد بيانات في الفترة هذه</div></td></tr>
                    @endforelse
                    </tbody>
                    @if (!empty($table['foot']) && count($table['rows']))
                        <tfoot><tr>@foreach ($table['foot'] as $i => $v)<td class="{{ in_array($i, $table['num'] ?? []) ? 'n num' : '' }}" style="font-weight:800;border-top:2px solid var(--ink)">{{ is_numeric($v) && in_array($i, $table['num'] ?? []) ? $f($v) : $v }}</td>@endforeach</tr></tfoot>
                    @endif
                </table>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
