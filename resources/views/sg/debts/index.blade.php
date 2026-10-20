@extends('sg.layout')
@section('title', 'الديون')
@php
    use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n);
    $whereLbl = ['shelf' => ['في الرف', 'u'], 'way' => ['في الطريق', 'b'], 'gone' => ['استلم ولم يُسدد', 'd']];
    $maxAge = max(1, collect($ageing)->max(fn ($a) => $a['rows']->sum('due')));
    $ageCol = ['w1' => '#c9cdd8', 'm1' => 'var(--s4)', 'm2' => 'var(--down)', 'old' => 'var(--down)'];
    $openPay = $payables->where('status', 'dept');
@endphp
@push('style')
<style>
.ageing { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 14px; }
.ag { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 12px 14px; position: relative; overflow: hidden; display: block; }
.ag.on { border: 2px solid var(--down); }
.ag .lb { font-size: 13px; color: var(--ink-2); } .ag .v { font-size: 22px; font-weight: 800; margin: 4px 0; } .ag .c { font-size: 12px; color: var(--ink-3); }
.ag .fill { position: absolute; bottom: 0; right: 0; height: 4px; }
.wh { display: flex; gap: 4px; flex-wrap: wrap; }
@media (max-width: 900px) { .ageing { grid-template-columns: 1fr 1fr; } }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>الديون</h1><div class="sub">كل أموال عند الناس لينا، وكل أموال علينا للناس</div></div>
    <div class="acts">
        <a class="btn ghost" target="_blank" href="{{ route('sg.debts.statement') }}">⬇ كشف PDF</a>
        <a class="btn ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">⬇ Excel</a>
        @can('dept-create')@php $remN = \App\Services\ReminderService::debtDue()->count(); @endphp<a class="btn green" href="{{ route('sg.debts.reminders') }}">💬 تذكيرات تستنى تأكيدك @if ($remN)<span class="bd" style="position:static;margin-right:6px">{{ $remN }}</span>@endif</a>@endcan
        <button class="sg-burger">☰</button>
    </div>
</div>

<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">عند العملاء لينا</div><div class="v"><span class="num">{{ $f($kpi['due']) }}</span> <small>د.ل</small></div><div class="s">{{ $kpi['customers'] }} عميل · {{ $kpi['orders'] }} إيصال</div></div>
    <div class="card kpi"><div class="lbl">منها بضاعة في الرف</div><div class="v plus"><span class="num">{{ $f($kpi['shelf']) }}</span> <small>د.ل</small></div><div class="s">تتقبض لما يجوا يستلموا</div></div>
    <div class="card kpi"><div class="lbl">منها لا يزال في الطريق</div><div class="v"><span class="num">{{ $f($kpi['way']) }}</span> <small>د.ل</small></div><div class="s">باقي العربون، بدون الشحن</div></div>
    <div class="card kpi red"><div class="lbl">استلموا ولم يسددوا</div><div class="v"><span class="num">{{ $f($kpi['gone']) }}</span> <small>د.ل</small></div><div class="s">{{ $kpi['goneN'] }} عملاء · هذه الخطيرة</div></div>
</div>

<nav class="tabs">
    <a href="?tab=customers" class="{{ $tab === 'customers' ? 'on' : '' }}">ديون العملاء <span class="b">{{ $kpi['customers'] }}</span></a>
    <a href="?tab=payables" class="{{ $tab === 'payables' ? 'on' : '' }}">علينا للناس <span class="b {{ $openPay->count() ? 'r' : '' }}">{{ $openPay->count() }}</span></a>
    <a href="?tab=credits" class="{{ $tab === 'credits' ? 'on' : '' }}">رصيد عملاء لدينا <span class="b">{{ $credits->count() }}</span></a>
    <a href="?tab=payments" class="{{ $tab === 'payments' ? 'on' : '' }}">سجل التسديدات</a>
</nav>

@if ($tab === 'customers')
    <div class="ageing">
        @foreach ($ageing as $k => $a)
            @php $sum = $a['rows']->sum('due'); @endphp
            <a href="{{ request()->fullUrlWithQuery(['age' => request('age') === $k ? null : $k]) }}" class="ag {{ request('age') === $k ? 'on' : '' }}"><div class="lb">{{ $a['label'] }}</div><div class="v num {{ in_array($k, ['m2', 'old']) && $sum ? 'minus' : '' }}">{{ $f($sum) }}</div><div class="c">{{ $a['rows']->count() }} عميل</div><span class="fill" style="width:{{ $sum / $maxAge * 100 }}%;background:{{ $ageCol[$k] }}"></span></a>
        @endforeach
    </div>
    <div class="card tw">
        <form class="tw-h" method="get">
            <input type="hidden" name="tab" value="customers">
            <div class="sx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" value="{{ request('q') }}" placeholder="كود عميل، اسم، هاتف…"></div>
            @foreach (['' => 'الكل', 'shelf' => 'في الرف', 'way' => 'في الطريق', 'gone' => 'استلم ولم يُسدد'] as $k => $v)<a class="pill {{ (string) request('w') === $k ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['w' => $k ?: null]) }}">{{ $v }}</a>@endforeach
            <select name="sort" class="pill" data-autosubmit>@foreach (['old' => 'رتّب: الأقدم أول', 'big' => 'الأكبر مبلغ', 'new' => 'الأحدث'] as $k => $v)<option value="{{ $k }}" @selected(request('sort', 'old') === $k)>{{ $v }}</option>@endforeach</select>
            @if (request('w'))<input type="hidden" name="w" value="{{ request('w') }}">@endif
        </form>
        <div class="scroll-x">
        <table class="t"><thead><tr><th>العميل</th><th>الإيصالات</th><th>أين البضاعة</th><th class="n">الفاتورة + الشحن</th><th>دفع</th><th class="n">الباقي</th><th>عمر الدين</th><th>آخر دفعة</th><th></th></tr></thead><tbody>
        @forelse ($rows as $r)
            @php $pc = $r->total > 0 ? min(100, $r->paid / $r->total * 100) : 0; @endphp
            <tr>
                <td class="cell2"><a href="{{ route('sg.clients', ['id' => $r->customer->id]) }}"><b>{{ $r->customer->name }}</b><small class="num">{{ $r->customer->code }} · {{ $r->customer->phone_1 }}</small></a></td>
                <td>@foreach ($r->orders->take(3) as $o)<a class="code" style="font-size:12.5px;margin-inline-end:4px" href="{{ route('sg.orders.show', $o) }}">{{ $o->code }}</a>@endforeach @if ($r->orders->count() > 3)<span class="note">+{{ $r->orders->count() - 3 }}</span>@endif</td>
                <td><div class="wh">@foreach ($whereLbl as $k => $v)@if ($r->$k)<span class="chip {{ $v[1] }}">{{ $v[0] }}{{ $r->$k > 1 ? ' ×' . $r->$k : '' }}</span>@endif @endforeach</div></td>
                <td class="n num">{{ $f($r->total) }}</td>
                <td style="min-width:110px"><div class="prog"><div class="b"><i style="width:{{ $pc }}%"></i></div></div><span class="note num">{{ $f($r->paid) }}</span></td>
                <td class="n"><b class="num minus">{{ $f($r->due) }}</b></td>
                <td><span class="chip {{ $r->age > 60 ? 'd' : ($r->age > 30 ? 'd' : ($r->age > 7 ? 'w' : 'n')) }}"><span class="num">{{ $r->age }}</span> يوم</span></td>
                <td class="note">{{ $r->last_pay ? \Carbon\Carbon::parse($r->last_pay)->diffForHumans() : 'لم يدفع' }}</td>
                <td style="display:flex;gap:4px">
                    @can('dept-create')
                    <button class="btn sm" data-modal="collect" data-cid="{{ $r->customer->id }}" data-cname="{{ $r->customer->name }}" data-due="{{ round($r->due, 2) }}">قبض</button>
                    <form method="post" action="{{ route('sg.debts.remind') }}" data-confirm="إرسال تذكير دين لـ {{ $r->customer->name }}؟">@csrf<input type="hidden" name="customer_id" value="{{ $r->customer->id }}"><button class="btn wa sm" title="تذكير واتساب">💬</button></form>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="9"><div class="empty"><span class="ic">🎉</span>لا يوجد ديون</div></td></tr>
        @endforelse
        </tbody></table>
        </div>
        <div class="tw-f"><span>المعروض <b class="num">{{ $rows->count() }}</b> من <b class="num">{{ $kpi['customers'] }}</b> عميل</span><span>مجموع الباقي <b class="num minus">{{ $f($rows->sum('due')) }}</b> د.ل</span></div>
    </div>

    @can('dept-create')
    <div class="modal-bg" id="collect"><div class="modal">
        <h3>قبض من <span id="cName"></span></h3>
        <form method="post" id="cForm" data-base="{{ route('sg.debts.collect', 0) }}">
            @csrf
            <div class="hint mb">عليه <b class="num" id="cDue"></b> د.ل · المبلغ يتوزع على إيصالاته من الأقدم</div>
            <div class="f"><label class="l">المبلغ</label><input name="amount" id="cAmt" class="inp big" required></div>
            <div class="f"><label class="l">دفع كيف</label><div class="opts">@foreach ($payments as $p)<label><input type="radio" name="payment_id" value="{{ $p->id }}" @checked($loop->first)><span>{{ $p->name }}</span></label>@endforeach</div></div>
            <label class="ck mb"><input type="checkbox" name="send_whatsapp" value="1" checked> أرسل له واتساب "استلمنا دفعتك"</label>
            <div style="display:flex;gap:8px"><button class="btn green">قبض</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
        </form>
    </div></div>
    @endcan

@elseif ($tab === 'payables')
    <div class="side-lay">
        <div class="card tw">
            <div class="tw-h"><h3>ديون على الشركة</h3><span class="note">سلف، بضاعة بالدين، أو أي مبلغ لازم نرجعه</span></div>
            <div class="scroll-x">
            <table class="t"><thead><tr><th>لمين</th><th>التاريخ</th><th>ملاحظة</th><th class="n">المبلغ</th><th class="n">سددنا</th><th class="n">الباقي</th><th></th></tr></thead><tbody>
            @forelse ($payables as $d)
                @php $paid = $d->paids->sum('paid_price'); $rest = $d->total - $paid; @endphp
                <tr style="{{ $d->status === 'paid' ? 'opacity:.55' : '' }}">
                    <td class="cell2"><b>{{ $d->org ?: optional($d->customer)->name }}</b><small class="num">{{ $d->phone ?: ($d->org ? optional($d->customer)->name : optional($d->customer)->code) }}</small></td>
                    <td class="note num">{{ $d->date }}@if ($d->due_date)<br><small style="{{ $d->status === 'dept' && $d->due_date < now()->toDateString() ? 'color:var(--down)' : '' }}">يُسدد {{ $d->due_date }}</small>@endif</td>
                    <td class="note wrap" style="max-width:220px">{{ $d->note }}</td>
                    <td class="n num">{{ $f($d->total) }}</td>
                    <td class="n num plus">{{ $f($paid) }}</td>
                    <td class="n">@if ($rest > 0.009)<b class="num minus">{{ $f($rest) }}</b>@else<span class="chip u">مدفوع</span>@endif</td>
                    <td>@if ($rest > 0.009)@can('dept-create')
                        <form method="post" action="{{ route('sg.debts.payable.pay', $d) }}" style="display:flex;gap:4px">@csrf<input name="amount" class="inp numr" style="height:34px;width:90px" value="{{ round($rest, 2) }}"><select name="payment_id" class="inp" style="height:34px;width:110px">@foreach ($payments as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select><button class="btn sm">سدد</button></form>
                    @endcan @endif</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><span class="ic">✓</span>ما علينا لحد شي</div></td></tr>
            @endforelse
            </tbody></table>
            </div>
            <div class="tw-f"><span>علينا الكل <b class="num minus">{{ $f($openPay->sum(fn ($d) => $d->total - $d->paids->sum('paid_price'))) }}</b> د.ل</span></div>
        </div>
        @can('dept-create')
        <div class="card">
            <h3>تسجيل دين على الشركة</h3>
            <form method="post" action="{{ route('sg.debts.payable.store') }}">
                @csrf
                <div class="f"><label class="l">اسم الدائن (اللي يبيهم منّا)</label><input name="org" class="inp" value="{{ old('org') }}" placeholder="شخص، محل، شركة…"></div>
                <div class="f"><label class="l">أو اختار من العملاء <span class="note">(اختياري)</span></label><select name="customer_id" class="inp"><option value="">— جهة خارجية —</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>@endforeach</select></div>
                <div class="f"><label class="l">رقم هاتفه <span class="note">(اختياري)</span></label><input name="phone" class="inp numr" value="{{ old('phone') }}"></div>
                <div class="grid g2 f"><div><label class="l">المبلغ (د.ل)</label><input name="total" class="inp numr" value="{{ old('total') }}" required></div><div><label class="l">تاريخ الدين</label><input type="date" name="date" class="inp" value="{{ old('date', now()->toDateString()) }}" required></div></div>
                <div class="f"><label class="l">موعد السداد <span class="note">(اختياري)</span></label><input type="date" name="due_date" class="inp" value="{{ old('due_date') }}"></div>
                <div class="f"><label class="l">التفاصيل / السبب</label><input name="note" class="inp" value="{{ old('note') }}" placeholder="سلفة، بضاعة بالدين…"></div>
                <label class="ck f"><input type="checkbox" name="into_treasury" value="1" onchange="this.form.querySelector('.tp').hidden=!this.checked"> المبلغ دخل للخزينة (استلفنا كاش)</label>
                <div class="f tp" hidden><label class="l">دخل في</label><select name="payment_id" class="inp">@foreach ($payments as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                <button class="btn block">حفظ</button>
                <div class="note mt">بدون الصح: التسجيل ما يحرّك الخزينة. لما تسدد، المبلغ يُصرف من الخزينة اللي تختارها.</div>
            </form>
        </div>
        @endcan
    </div>

@elseif ($tab === 'credits')
    <div class="card tw">
        <div class="tw-h"><h3>رصيد العملاء لدينا</h3><span class="note">أموال للعميل (قطع ناقصة، زيادة دفع…) تُخصم من طلبه القادم عند التسليم</span></div>
        <table class="t"><thead><tr><th>العميل</th><th>الهاتف</th><th>ملاحظات</th><th class="n">الرصيد</th></tr></thead><tbody>
        @forelse ($credits as $c)
            <tr><td class="cell2"><a href="{{ route('sg.clients', ['id' => $c->id]) }}"><b>{{ $c->name }}</b><small class="num">{{ $c->code }}</small></a></td><td class="num note">{{ $c->phone_1 }}</td><td class="note wrap">{{ \Illuminate\Support\Str::limit($c->notes, 60) }}</td><td class="n"><b class="num" style="color:var(--s1)">{{ $f($c->credit) }}</b></td></tr>
        @empty
            <tr><td colspan="4"><div class="empty">لا يوجد</div></td></tr>
        @endforelse
        </tbody></table>
        <div class="tw-f"><span>المجموع <b class="num">{{ $f($credits->sum('credit')) }}</b> د.ل</span></div>
    </div>

@else
    <div class="card tw">
        <div class="tw-h"><h3>سجل التسديدات</h3><span class="note">كل أموال دخلت على الإيصالات</span></div>
        <div class="scroll-x">
        <table class="t"><thead><tr><th>الوقت</th><th>العميل</th><th>الإيصال</th><th>الطريقة</th><th class="n">المبلغ</th></tr></thead><tbody>
        @foreach ($history as $h)
            <tr><td class="note num">{{ optional($h->created_at)->format('Y-m-d g:i') }}</td><td class="cell2"><b>{{ optional($h->customer)->name }}</b><small class="num">{{ optional($h->customer)->code }}</small></td><td>@if ($h->order)<a class="code" href="{{ route('sg.orders.show', $h->order) }}">{{ $h->order->code }}</a>@endif</td><td><span class="chip n">{{ optional($h->paymentMethod)->name ?: '—' }}</span></td><td class="n"><b class="num plus">+{{ $f($h->paid_price) }}</b></td></tr>
        @endforeach
        </tbody></table>
        </div>
        <div class="tw-f">@include('sg.partials.pager', ['p' => $history])</div>
    </div>
@endif
@endsection
@push('script')
<script>
document.querySelectorAll('[data-cid]').forEach(b => b.addEventListener('click', () => {
  const f = document.getElementById('cForm');
  f.action = f.dataset.base.replace(/0$/, b.dataset.cid);
  document.getElementById('cName').textContent = b.dataset.cname;
  document.getElementById('cDue').textContent = SG.fmt(b.dataset.due);
  document.getElementById('cAmt').value = b.dataset.due;
}));
</script>
@endpush
