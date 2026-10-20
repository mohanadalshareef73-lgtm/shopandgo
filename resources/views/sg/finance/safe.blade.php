@extends('sg.layout')
@section('title', 'المالية · الخزينة')
@php
    use App\Services\TreasuryService as TS; use App\Http\Controllers\Sg\FinanceController as FC;
    $f = fn ($n) => TS::fmt($n);
    $ic = ['rec' => ['in', '🧾'], 'trip' => ['out', '✈'], 'usd' => ['x', '⇄'], 'exp' => ['out', '🧾'], 'ship' => ['out', '🚚'], 'prt' => ['n', '👤'], 'n' => ['n', '•']];
    $chg = $startDinar ? ($totalDinar - $startDinar) / abs($startDinar) * 100 : 0;
    $days = $ledger->getCollection()->groupBy(fn ($h) => $h->created_at->toDateString());
@endphp
@section('content')
@include('sg.finance._head')

<div class="wallets">
    @foreach ($wallets as $w)
        <div class="wallet c{{ $loop->index % 5 }}">
            <div class="w-top"><span>{{ $w->name }}{{ $w->cur !== 'dinar' ? ' · ' . TS::CURRENCY_NAMES[$w->cur] : '' }}</span><span>{{ $w->pid == $cashId && $w->cur === 'dinar' ? '💵' : ($w->cur === 'dollar' ? '💳' : '🏦') }}</span></div>
            <div class="w-bal"><span class="num">{{ $f($w->bal) }}</span> <small>{{ TS::sign($w->cur) }}</small></div>
            <div class="w-sub">@if ($w->cur === 'dollar' && $rate)≈ <span class="num">{{ $f($w->bal * $rate) }}</span> د.ل · بسعر {{ $rate }}@else{{ $w->pid == $cashId ? 'في الصندوق الآن' : 'رصيد الحساب' }}@endif</div>
            <div class="w-io"><span>▲ دخل <b class="num">{{ $f($w->in) }}</b></span><span>▼ طلع <b class="num">{{ $f($w->out) }}</b></span></div>
        </div>
    @endforeach
    @php $shCards = \Illuminate\Support\Facades\Schema::hasTable('shein_cards') ? \App\Models\SheinCard::whereIn('status', ['new', 'partial'])->where('balance', '>', 0)->get(['balance']) : collect(); @endphp
    <a class="wallet c3" href="{{ route('sg.shein') }}" style="text-decoration:none;color:inherit">
        <div class="w-top"><span>بطاقات SHEIN</span><span>🛍</span></div>
        <div class="w-bal"><span class="num">{{ $f($shCards->sum('balance')) }}</span> <small>$</small></div>
        <div class="w-sub">{{ $shCards->count() }} بطاقة فيها رصيد · غير محسوبة في المجموع</div>
        <div class="w-io"><span>للاستخدام في الرحلات أو البيع</span></div>
    </a>
    @php $crs = \App\Services\CourierService::couriers(); $held = 0; $owe = 0; $offN = 0; foreach ($crs as $cr) { $e = \App\Services\CourierService::balance($cr)['expected']; $e > 0 ? $held += $e : $owe += -$e; $offN += $cr->isOffice() ? 1 : 0; } @endphp
    <a class="wallet c1" href="{{ route('sg.couriers') }}" style="text-decoration:none;color:inherit">
        <div class="w-top"><span>عند المندوبين ومكاتب التوصيل</span><span>🚚</span></div>
        <div class="w-bal"><span class="num">{{ $f($held) }}</span> <small>د.ل</small></div>
        <div class="w-sub">{{ $owe > 0 ? 'وعلينا لهم أجرة ' . $f($owe) . ' د.ل' : 'تُقفل من صفحة المندوبين' }}{{ $offN ? ' · ' . $offN . ' مكتب' : '' }}</div>
        <div class="w-io"><span>غير محسوبة في المجموع حتى الإقفال</span></div>
    </a>
    <div class="card">
        @php $capL = collect($wallets)->where('cur', 'dinar')->sum('bal'); $capU = collect($wallets)->where('cur', 'dollar')->sum('bal'); @endphp
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div><div class="note">رأس المال بالدينار</div><div class="big-num"><span class="num">{{ $f($capL) }}</span> <small class="note" style="font-size:14px">د.ل</small></div></div>
            <div><div class="note">رأس المال بالدولار</div><div class="big-num"><span class="num">{{ $f($capU) }}</span> <small class="note" style="font-size:14px">$</small></div></div>
        </div>
        <div class="note">الكل محوّل لدينار: <b class="num">{{ $f($totalDinar) }}</b> د.ل</div>
        <span class="chip {{ $chg >= 0 ? 'u' : 'd' }}">{{ $chg >= 0 ? '▲' : '▼' }} <span class="num">{{ number_format(abs($chg), 1) }}%</span></span> <span class="note">مقارنة ببداية {{ $period->label }}</span>
        @php $tot = max(1, collect($wallets)->sum(fn ($w) => max(0, $toDinar($w)))); $cl = ['#262c4a', '#2a78d6', '#0f8a5f', '#6250d6', '#b5480f']; @endphp
        <div class="split">@foreach ($wallets as $w)@if ($toDinar($w) > 0)<span style="width:{{ $toDinar($w) / $tot * 100 }}%;background:{{ $cl[$loop->index % 5] }}" title="{{ $w->name }}"></span>@endif @endforeach</div>
    </div>
</div>

<div class="actions">
    @can('dept-list')<a class="act" href="{{ route('sg.debts') }}"><span class="ai in">＋</span><div>قبض من عميل<small>عربون أو باقي</small></div></a>@endcan
    <a class="act" href="{{ route('sg.finance', ['tab' => 'usd']) }}"><span class="ai x">⇄</span><div>شراء دولار<small>دينار ← فيزا</small></div></a>
    @can('purchase-create')<a class="act" href="{{ route('sg.expenses') }}"><span class="ai out">−</span><div>مصروف<small>إيجار، انترنت…</small></div></a>@endcan
    <a class="act" href="{{ route('sg.finance', ['tab' => 'ship']) }}"><span class="ai out">🚚</span><div>دفع شركة الشحن<small>حسب الوزن والطرود</small></div></a>
    @can('partner-create')<button class="act" data-modal="mPartner"><span class="ai n">👥</span><div>حركة شريك<small>سحب، مصروف شخصي، تسديد</small></div></button>@endcan
    @role('Admin')<a class="act" href="{{ route('sg.finance.adjust.form') }}"><span class="ai w">⚖</span><div>ضبط الأرصدة (جرد)<small>كل الخزائن بكل العملات</small></div></a>@endrole
    @can('treasury-create')<button class="act" data-modal="mTransfer"><span class="ai x">→</span><div>تحويل بين الخزائن<small>نقداً ↔ مصرف</small></div></button>@endcan
</div>

<div class="side-lay">
    <div class="card tw">
        <form class="tw-h" method="get">
            <input type="hidden" name="tab" value="safe"><input type="hidden" name="p" value="{{ $period->preset }}">
            <h3>حركة الخزينة <span class="h-note">كل حركة باسم الموظف الذي داراها</span></h3>
            @foreach (['' => 'الكل', 'in' => 'دخل', 'out' => 'طلع'] as $k => $v)<a class="pill {{ (string) request('dir') === $k ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['dir' => $k ?: null, 'page' => null]) }}">{{ $v }}</a>@endforeach
            <select name="pm" class="pill" data-autosubmit><option value="">كل الخزائن</option>@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected(request('pm') == $pm->id)>{{ $pm->name }}</option>@endforeach</select>
            <div class="sx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" value="{{ request('q') }}" placeholder="بحث: رقم إيصال، عميل، رحلة…"></div>
            @if (request('dir'))<input type="hidden" name="dir" value="{{ request('dir') }}">@endif
            <a class="btn ghost sm" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">⬇</a>
        </form>
        @forelse ($days as $d => $rows)
            @php $dd = \Carbon\Carbon::parse($d); $dinIn = $rows->where('currency', 'dinar')->where('status', 'pay')->sum('price'); $dinOut = $rows->where('currency', 'dinar')->where('status', 'pull')->sum('price'); $usdOut = $rows->where('currency', 'dollar')->where('status', 'pull')->sum('price'); @endphp
            <div class="day-h"><b>{{ $dd->isToday() ? 'اليوم · ' : ($dd->isYesterday() ? 'أمس · ' : '') }}{{ \App\Services\Period::DAYS[$dd->dayOfWeek] }} {{ $dd->day }} {{ \App\Services\Period::MONTHS[$dd->month - 1] }}</b><span class="io"><span class="plus">▲ دخل <b class="num">{{ $f($dinIn) }}</b> د.ل</span><span class="minus">▼ طلع <b class="num">{{ $f($dinOut) }}</b> د.ل</span>@if ($usdOut)<span class="minus"><b class="num">{{ $f($usdOut) }}</b> $</span>@endif</span></div>
            <table class="t led"><tbody>
            @foreach ($rows as $h)
                @php $t = FC::TYPES[$h->type] ?? [$h->type, 'n']; $in = $h->status === 'pay'; $i = $ic[$t[1]] ?? $ic['n']; @endphp
                <tr>
                    <td style="width:44px"><span class="ti ai {{ $in ? 'in' : $i[0] }}">{{ $i[1] }}</span></td>
                    <td class="wrap"><b style="font-size:14px">{{ $t[0] }}</b><div class="note">{{ $h->note ?: '' }}</div></td>
                    <td><span class="chip n">{{ optional($h->payment)->name }}</span></td>
                    <td class="note">{{ optional($h->createdUser)->name }}</td>
                    <td class="note num">{{ $h->created_at->format('g:i') }}</td>
                    <td class="n"><b class="num {{ $in ? 'plus' : 'minus' }}">{{ $in ? '+' : '−' }}{{ $f($h->price) }}</b> <span class="note">{{ TS::sign($h->currency) }}</span><div class="note num">الرصيد {{ $f($h->price_after) }}</div></td>
                </tr>
            @endforeach
            </tbody></table>
        @empty
            <div class="empty"><span class="ic">💤</span>لا يوجد حركة في الفترة هذه</div>
        @endforelse
        <div class="tw-f">@include('sg.partials.pager', ['p' => $ledger])</div>
    </div>

    <div>
        <div class="card close-card mb">
            <h3>تقفيل الكاش <span class="h-note">ورديتك من {{ $shift['since']->format('g:i A') }}</span></h3>
            <div class="ln"><span>رصيد البداية</span><b class="num">{{ $f($shift['opening']) }}</b></div>
            <div class="ln"><span class="plus">+ قبضت نقداً</span><b class="num plus">{{ $f($shift['cash_in']) }}</b></div>
            <div class="ln"><span class="minus">− صرفت نقداً</span><b class="num minus">{{ $f($shift['cash_out']) }}</b></div>
            <div class="ln tot"><span>المفروض معك</span><b class="num" id="exp" data-v="{{ $shift['expected'] }}">{{ $f($shift['expected']) }}</b></div>
            @if ($shift['other_in'])<div class="note">+ دخل على المصرف {{ $f($shift['other_in']) }} (لا يدخل في الدرج)</div>@endif
            <form class="count" method="post" action="{{ route('sg.finance.close') }}" data-confirm="نقفلو الوردية؟">
                @csrf
                <label class="l">هل عددت النقد؟ اكتب المبلغ الموجود</label>
                <div style="display:flex;gap:8px"><input name="counted" id="cnt" class="inp numr" required inputmode="decimal"><button class="btn">تقفيل</button></div>
                <div class="note" style="margin-top:8px" id="diff"></div>
                <input name="note" class="inp mt" style="height:38px" placeholder="ملاحظة (اختياري)">
            </form>
            @if ($lastClosed)<div class="note mt">آخر تقفيل: {{ $lastClosed->closed_at->diffForHumans() }} · {{ optional($lastClosed->user)->name }} · {{ abs($lastClosed->diff) < 0.01 ? 'بدون فرق ✓' : 'فرق ' . $f($lastClosed->diff) }}</div>@endif
        </div>
    </div>
</div>

@can('partner-create')
<div class="modal-bg" id="mPartner"><div class="modal">
    <h3>حركة شريك</h3>
    <form method="post" action="{{ route('finance.partners.transactions.store') }}">
        @csrf
        <div class="f"><div class="opts" style="flex-wrap:wrap"><label><input type="radio" name="type" value="withdrawal" checked><span>سحب يومي</span></label><label><input type="radio" name="type" value="expense"><span>مصروف شخصي (دين عليه)</span></label><label><input type="radio" name="type" value="settle"><span>تسديد دين (يدخل الخزينة)</span></label></div>
            <div class="note" style="margin-top:6px">«مصروف شخصي» يُصرف من الخزينة ويُسجَّل ديناً على الشريك، ويمكن خصمه من نصيبه عند توزيع الفائض.</div></div>
        @forelse ($partners as $pt)
            <div class="f"><label class="l">{{ $pt->name }}</label><input name="amounts[{{ $pt->id }}]" class="inp numr" placeholder="0"></div>
        @empty
            <div class="hint mb">لا يوجد شركاء — زيدهم من تبويب الأرباح والشركاء</div>
        @endforelse
        <div class="grid g2 f">
            <div><label class="l">من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }}</option>@endforeach</select></div>
            <div><label class="l">التاريخ</label><input type="date" name="date" class="inp" value="{{ now()->toDateString() }}"></div>
        </div>
        <div class="f"><label class="l">السبب / ملاحظة</label><input name="note" class="inp" placeholder="مثلاً: فاتورة هاتف، بنزين…"></div>
        <input type="hidden" name="currency" value="dinar">
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan
@can('treasury-create')
<div class="modal-bg" id="mTransfer"><div class="modal">
    <h3>تحويل بين الخزائن</h3>
    <form method="post" action="{{ route('sg.finance.transfer') }}">
        @csrf
        <div class="grid g2 f">
            <div><label class="l">من</label><select name="from_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}">{{ $pm->name }}</option>@endforeach</select></div>
            <div><label class="l">إلى</label><select name="to_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($loop->index === 1)>{{ $pm->name }}</option>@endforeach</select></div>
        </div>
        <div class="grid g2 f">
            <div><label class="l">المبلغ</label><input name="amount" class="inp numr" required></div>
            <div><label class="l">العملة</label><select name="currency" class="inp">@foreach (TS::CURRENCY_NAMES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
        </div>
        <div class="f"><input name="note" class="inp" placeholder="ملاحظة (اختياري)"></div>
        <div style="display:flex;gap:8px"><button class="btn">تحويل</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan
@endsection
@push('script')
<script>
(function () {
  const c = document.getElementById('cnt'), e = document.getElementById('exp'), d = document.getElementById('diff');
  if (!c) return;
  c.addEventListener('input', () => {
    if (c.value === '') { d.textContent = ''; return; }
    const v = (parseFloat(c.value) || 0) - parseFloat(e.dataset.v);
    d.innerHTML = Math.abs(v) < 0.01 ? '<b class="plus">✓ مضبوط</b>' : 'الفرق: <b class="num ' + (v < 0 ? 'minus' : 'plus') + '">' + (v > 0 ? '+' : '') + SG.fmt2(v) + ' د.ل</b> · يُسجَّل في سجل التقفيل';
  });
})();
</script>
@endpush
