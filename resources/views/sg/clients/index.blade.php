@extends('sg.layout')
@section('title', 'العملاء')
@php
    use App\Services\TreasuryService as TS; use App\Http\Controllers\Sg\ClientsController as CC;
    $f = fn ($n) => TS::fmt($n, 0);
    $palette = ['#2a78d6', '#6250d6', '#eb6834', '#1baf7a', '#262c4a', '#1d4f8f', '#0f6b4c', '#b5480f'];
    $tagOf = function ($c) {
        if ($c->is_vip) return ['⭐ VIP', 'vip'];
        if ($c->created_at && $c->created_at->gte(now()->startOfMonth())) return ['جديد', 'b'];
        if ($c->due > 0.009 && $c->last_at && \Carbon\Carbon::parse($c->last_at)->lt(now()->subDays(45))) return ['دين قديم', 'd'];
        if (!$c->last_at || \Carbon\Carbon::parse($c->last_at)->lt(now()->subDays(60))) return ['خامل', 'n'];
        return null;
    };
    $when = function ($d) {
        if (!$d) return '—';
        $d = \Carbon\Carbon::parse($d);
        if ($d->isToday()) return 'اليوم';
        if ($d->isYesterday()) return 'أمس';
        return $d->gte(now()->subDays(30)) ? $d->day . ' ' . \App\Services\Period::MONTHS[$d->month - 1] : \App\Services\Period::MONTHS[$d->month - 1] . ($d->year != now()->year ? ' ' . $d->year : '');
    };
@endphp
@push('style')
<style>
.prof { padding: 0; overflow: hidden; position: sticky; top: 16px; }
.prof .ph { background: var(--brand); color: #fff; padding: 18px; }
.prof .ph .nm { font-size: 19px; font-weight: 800; }
.prof .ph .mt2 { font-size: 13px; color: #c9cde0; margin-top: 2px; }
.prof .ph .av { width: 50px; height: 50px; font-size: 20px; background: rgba(255,255,255,.16); }
.prof .acts2 { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 14px; }
.prof .acts2 a, .prof .acts2 button { background: rgba(255,255,255,.1); color: #fff; border: 0; border-radius: 10px; padding: 9px 4px; font: inherit; font-size: 12.5px; text-align: center; cursor: pointer; }
.prof .st3 { display: grid; grid-template-columns: repeat(3, 1fr); border-bottom: 1px solid var(--line-soft); }
.prof .st3 div { padding: 12px 14px; border-left: 1px solid var(--line-soft); }
.prof .st3 div:last-child { border-left: 0; }
.prof .st3 small { display: block; font-size: 12px; color: var(--ink-3); }
.prof .st3 b { font-size: 19px; }
.prof .sec { padding: 14px 18px; border-bottom: 1px solid var(--line-soft); }
.prof h4 { margin: 0 0 8px; font-size: 14.5px; display: flex; justify-content: space-between; }
.kv { display: flex; justify-content: space-between; font-size: 13.5px; padding: 5px 0; gap: 10px; }
.kv span { color: var(--ink-3); }
.hist .it { display: flex; align-items: center; gap: 10px; padding: 7px 0; }
.hist .it .r { margin-inline-start: auto; text-align: left; }
.chip.vip { background: #fff3d6; color: #8a5a00; }
.tagx { font-size: 11px; padding: 1px 7px; margin-inline-start: 4px; vertical-align: middle; }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>العملاء</h1><div class="sub">كل عميل: بياناته، طلباته، كم صرف، وكم عليه</div></div>
    <div class="acts">@role('Admin')<button type="button" class="btn ghost" data-modal="mTrust">✅ الموثوقين</button>@endrole @can('profit-view')<a class="btn ghost" href="{{ route('sg.clients.profit') }}">📊 ربح العملاء</a>@endcan
        <a class="btn ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">⬇ تصدير الأرقام</a>
        @can('customer-create')<button class="btn" data-modal="newCust">+ عميل جديد</button>@endcan
        <button class="sg-burger">☰</button>
    </div>
</div>

<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">كل العملاء</div><div class="v num">{{ number_format($kpi['total']) }}</div><div class="s">من أول ما بدينا</div></div>
    <div class="card kpi"><div class="lbl">عملاء جدد الشهر هذا</div><div class="v num">{{ number_format($kpi['newCur']) }}</div><div class="s">@include('sg.partials.chg', ['cur' => $kpi['newCur'], 'prev' => $kpi['newPrev']]) {{ $prevMonth }} {{ number_format($kpi['newPrev']) }}</div></div>
    <div class="card kpi"><div class="lbl">نشطين (طلبوا آخر 30 يوم)</div><div class="v num">{{ number_format($kpi['active']) }}</div><div class="s">{{ $kpi['total'] ? round($kpi['active'] / $kpi['total'] * 100) : 0 }}% من العملاء</div></div>
    <div class="card kpi"><div class="lbl">عادوا للطلب مرة ثانية</div><div class="v"><span class="num">{{ $kpi['repeat'] }}%</span></div><div class="s">من الذي طلبوا مرة على الأقل</div></div>
</div>

<div class="side-lay" style="grid-template-columns:1fr 380px">
    <div class="card tw">
        <form class="tw-h" method="get">
            <div class="sx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" value="{{ request('q') }}" placeholder="اسم، كود، هاتف…"></div>
            @foreach (['' => 'الكل', 'vip' => '⭐ VIP', 'new' => 'جدد', 'debt' => 'عليهم ديون', 'idle' => 'خاملين'] as $k => $v)
                <a class="pill {{ (string) $tag === $k ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['tag' => $k ?: null, 'page' => null, 'id' => null]) }}">{{ $v }}</a>
            @endforeach
            <select name="sort" class="pill" data-autosubmit>@foreach (['last' => 'ترتيب: آخر طلب', 'spent' => 'الأكثر صرف', 'due' => 'الأكثر دين', 'orders' => 'الأكثر طلبات'] as $k => $v)<option value="{{ $k }}" @selected(request('sort', 'last') === $k)>{{ $v }}</option>@endforeach</select>
            @if ($tag)<input type="hidden" name="tag" value="{{ $tag }}">@endif
        </form>
        <div class="scroll-x">
        @if ($tag === 'idle')@can('customer-edit')
        <form method="post" action="{{ route('sg.clients.bulk') }}" id="bulkWa" style="padding:12px 16px;border-bottom:1px solid var(--line);background:#f3fbf6">
            @csrf
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px"><b>💬 رسالة تنشيط للخاملين <span class="note">كل عميل توصله رسالته بروحه</span></b><label class="ck"><input type="checkbox" id="pickAll"> اختار الكل في الصفحة</label></div>
            <textarea name="body" class="inp" rows="3" required placeholder="اهلا {الاسم} 👋 وحشتنا! عندنا عروض جديدة… كودك {الكود}"></textarea>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px"><span class="note">{الاسم} و {الكود} يتبدلوا لكل عميل · المحددين: <b class="num" id="pickN">0</b></span><button class="btn wa sm" data-confirm="نرسلو للعملاء المحددين؟">أرسل</button></div>
        </form>
        @endcan @endif
        <table class="t"><thead><tr>@if ($tag === 'idle')<th style="width:34px"></th>@endif<th>العميل</th><th>العنوان</th><th class="n">الطلبات</th><th class="n">صرف</th><th class="n">عليه</th><th>آخر طلب</th></tr></thead><tbody>
        @forelse ($list as $c)
            @php $tg = $tagOf($c); @endphp
            <tr class="{{ $sel && $sel->id === $c->id ? 'on' : '' }}" style="cursor:pointer" onclick="location.href='{{ request()->fullUrlWithQuery(['id' => $c->id]) }}'">
                @if ($tag === 'idle')<td onclick="event.stopPropagation()"><input type="checkbox" class="wpick" name="ids[]" value="{{ $c->id }}" form="bulkWa"></td>@endif
                <td><div class="who"><span class="av" style="background:{{ $palette[$c->id % 8] }};width:34px;height:34px;font-size:14px">{{ mb_substr($c->name, 0, 1) }}</span><div class="cell2"><b>{{ $c->name }} @if ($tg)<span class="chip {{ $tg[1] }} tagx">{{ $tg[0] }}</span>@endif</b><small class="num">{{ $c->code }} · {{ $c->phone_1 }}</small></div></div></td>
                <td class="note">{{ \Illuminate\Support\Str::limit($c->address, 22) ?: '—' }}</td>
                <td class="n num">{{ $c->orders_n }}</td>
                <td class="n num">{{ $f($c->spent) }}</td>
                <td class="n">@if ($c->due > 0.009)<b class="num minus">{{ $f($c->due) }}</b>@else<span class="note">✓</span>@endif</td>
                <td class="note">{{ $when($c->last_at) }}</td>
            </tr>
        @empty
            <tr><td colspan="6"><div class="empty"><span class="ic">👥</span>لا يوجد عملاء بالبحث هذا</div></td></tr>
        @endforelse
        </tbody></table>
        </div>
        <div class="tw-f"><span>المعروض <b class="num">{{ $list->count() }}</b> من <b class="num">{{ number_format($list->total()) }}</b></span>@include('sg.partials.pager', ['p' => $list])</div>
    </div>

    @if ($sel)
    @php $tg = $tagOf($sel); $wa = \App\Services\MessageService::normalize($sel->phone_1 ?? ''); @endphp
    <div class="card prof">
        <div class="ph">
            <div class="who"><span class="av">{{ mb_substr($sel->name, 0, 1) }}</span><div><div class="nm">{{ $sel->name }}</div><div class="mt2"><span class="num">{{ $sel->code }}</span> · <span class="num">{{ $sel->phone_1 }}</span>{{ $sel->phone_2 ? ' · ' : '' }}<span class="num">{{ $sel->phone_2 }}</span>{{ $sel->address ? ' · ' . $sel->address : '' }}</div></div></div>
            @php $lvS = \App\Services\LoyaltyService::info($sel); @endphp
            <div style="margin-top:10px;display:flex;gap:6px;flex-wrap:wrap">@if ($lvS && $lvS['tier'])<span class="chip" style="background:{{ $lvS['tier'][5] }};color:#fff">{{ $lvS['tier'][2] }} {{ $lvS['tier'][1] }}</span>@endif @if ($tg)<span class="chip {{ $tg[1] }}">{{ $tg[0] }}</span>@endif<span class="chip" style="background:rgba(255,255,255,.12);color:#fff">عميل من {{ \App\Services\Period::MONTHS[$sel->created_at->month - 1] }} {{ $sel->created_at->year }}</span>@if ($sel->credit > 0)<span class="chip u">رصيد لدينا {{ TS::fmt($sel->credit) }}</span>@endif</div>
            <div class="acts2">
                <a href="tel:{{ $sel->phone_1 }}">📞 اتصال</a>
                <a href="https://wa.me/{{ $wa }}" target="_blank">💬 واتساب</a>
                @can('order-create')<a href="{{ route('sg.orders.create', ['phone' => $sel->phone_1]) }}">+ إيصال</a>@endcan
                <a href="{{ route('sg.orders.index', ['q' => $sel->code]) }}">📄 طلباته</a>
            </div>
        </div>
        <div class="st3">
            <div><small>طلبيات</small><b class="num">{{ $sel->orders_n }}</b></div>
            <div><small>صرف في الكل</small><b class="num">{{ $f($sel->spent) }}</b></div>
            <div><small>عليه الآن</small><b class="num {{ $sel->due > 0.009 ? 'minus' : '' }}">{{ $f($sel->due) }}</b></div>
        </div>
        <div class="sec">
            <h4>كم يصرف كل شهر <span class="note">آخر 8 شهور</span></h4>
            <svg class="spark" data-spark="{{ json_encode($prof['spark']) }}"></svg>
            <div class="kv"><span>متوسط الطلبية</span><b class="num">{{ $f($prof['avg']) }} د.ل</b></div>
            <div class="kv"><span>أكثر منصة</span><b>{{ $prof['platforms']->map(fn ($n, $k) => "$k ($n)")->implode(' · ') ?: '—' }}</b></div>
            <div class="kv"><span>يستلم عادة</span><b>{{ $prof['delivery'] ?: '—' }}{{ $prof['shelf'] !== null ? ' · بعد ' . $prof['shelf'] . ' يوم من الوصول' : '' }}</b></div>
        </div>
        <div class="sec hist">
            <h4>آخر طلبات <a class="note" style="color:var(--s1)" href="{{ route('sg.orders.index', ['q' => $sel->code]) }}">الكل ←</a></h4>
            @forelse ($prof['orders'] as $o)
                @php $rem = $o->remaining(); @endphp
                <a class="it" href="{{ route('sg.orders.show', $o) }}"><span class="code">{{ $o->code }}</span><span class="st {{ $o->stage() }}"><i></i>{{ $o->stageLabel() }}</span><div class="r"><b class="num {{ $rem > 0.009 ? 'minus' : '' }}">{{ $f($rem > 0.009 ? $rem : $o->total_price) }}</b><div class="note">{{ $rem > 0.009 ? 'باقي' : $when($o->created_at) }}</div></div></a>
            @empty
                <div class="note">لم يطلب لا يزال</div>
            @endforelse
            @foreach ($prof['depts'] as $d)
                <div class="it"><span class="chip b">علينا له</span><span class="note">{{ $d->note }}</span><div class="r"><b class="num">{{ $f($d->total - $d->paids->sum('paid_price')) }}</b></div></div>
            @endforeach
        </div>
        @can('trip-purchase-edit')
        @php $selFree = \App\Http\Controllers\TripPurchaseController::freeOrdersQuery()->where('customer_id', $sel->id)->latest()->get(); @endphp
        <div class="sec">
            <h4>✈ إضافة لرحلة <span class="note">{{ $selFree->count() }} إيصال برا الرحلات</span></h4>
            @if ($selFree->isEmpty())
                <div class="note">كل إيصالاته داخل رحلات ✓</div>
            @else
            <form method="post" action="{{ route('finance.trips.attachTo') }}">@csrf
                <select name="trip_id" class="inp" required style="margin-bottom:6px"><option value="">— اختار الرحلة —</option>
                    @foreach (\App\Models\TripPurchase::where(fn ($w) => $w->where('legacy', false)->orWhereNull('legacy'))->latest('id')->take(30)->get() as $tp)<option value="{{ $tp->id }}">{{ $tp->trip_code }}{{ $tp->purchased_at ? ' · ' . $tp->purchased_at->format('Y-m-d') : '' }}</option>@endforeach
                </select>
                @foreach ($selFree as $o)
                    <label class="ck" style="font-size:12.5px"><input type="checkbox" name="order_ids[]" value="{{ $o->id }}" checked> <span class="num">{{ $o->code }}</span> · {{ $o->qty }} قطعة · {{ $f($o->total_price) }}</label>
                @endforeach
                <label class="ck" style="font-size:12.5px"><input type="checkbox" name="send_whatsapp" value="1" checked> ابعتله رسالة «طلبك دخل رحلة»</label>
                <button class="btn green sm" style="margin-top:4px">✈ زيدهم للرحلة</button>
            </form>
            @endif
        </div>
        @endcan
        <div class="sec">
            <h4>👑 VIP و الرصيد <span class="note">رصيده لدينا <b class="num">{{ TS::fmt($sel->credit) }}</b> د.ل</span></h4>
            @role('Admin')@if (\Illuminate\Support\Facades\Schema::hasColumn('customers', 'trusted'))<form method="post" action="{{ route('sg.clients.trusted', $sel) }}" style="margin-bottom:8px">@csrf<button class="btn {{ $sel->trusted ? 'ghost' : 'green' }} sm">{{ $sel->trusted ? '✕ شيل من الموثوقين' : '✅ خليه موثوق (بدون عربون)' }}</button>@if ($sel->trusted) <span class="chip u">✅ موثوق</span>@endif</form>@endif @endrole
            @role('Admin')<form method="post" action="{{ route('sg.clients.vip', $sel) }}" style="margin-bottom:8px">@csrf<button class="btn {{ $sel->is_vip ? 'ghost' : '' }} sm">{{ $sel->is_vip ? '✕ شيل VIP' : '⭐ خليه VIP' }}</button></form>@endrole
            @can('customer-edit')<form method="post" action="{{ route('sg.clients.credit', $sel) }}">@csrf
                <div style="display:flex;gap:6px"><input name="amount" class="inp numr" style="height:36px;width:90px" placeholder="+/- د.ل" required><input name="reason" class="inp" style="height:36px" placeholder="السبب (اختياري)"><button class="btn ghost sm">حفظ</button></div>
                <label class="ck" style="margin-top:6px;font-size:13px"><input type="checkbox" name="treasury" value="1" onchange="this.form.querySelector('.tp').hidden=!this.checked"> المبلغ دخل/طلع من الخزينة</label>
                <select name="payment_id" class="inp tp" style="height:36px;margin-top:4px" hidden>@foreach (\App\Models\PaymentMethod::where('status', true)->get() as $pm)<option value="{{ $pm->id }}">{{ $pm->name }}</option>@endforeach</select>
            </form>@endcan
        </div>
        <div class="sec">
            <h4>⭐ النقاط <span class="num">{{ (int) $sel->points }}</span> <span class="note">= {{ TS::fmt($sel->points * \App\Services\PointsService::value()) }} د.ل</span></h4>
            @foreach ($prof['pointsLog'] as $pl)<div class="kv"><span>{{ $pl->reason }} · {{ $pl->created_at->format('j/n') }}</span><b class="num {{ $pl->points > 0 ? 'plus' : 'minus' }}">{{ $pl->points > 0 ? '+' : '' }}{{ $pl->points }}</b></div>@endforeach
            @can('points-edit')<form method="post" action="{{ route('sg.clients.points', $sel) }}" style="display:flex;gap:6px;margin-top:8px">@csrf<input name="points" class="inp numr" style="height:36px;width:80px" placeholder="+/-"><input name="reason" class="inp" style="height:36px" placeholder="السبب"><button class="btn ghost sm">حفظ</button></form>@endcan
        </div>
        <div class="sec">
            <h4>📤 رسالة للعميل <span class="note">تظهر في حسابه + واتساب</span></h4>
            @foreach ($prof['messages'] as $m)<div class="kv"><span style="color:var(--ink)">{{ \Illuminate\Support\Str::limit($m->body, 60) }}</span><small class="note">{{ $m->created_at->format('j/n') }} {{ $m->sent ? '✓' : '' }}</small></div>@endforeach
            @can('customer-edit')<form method="post" action="{{ route('sg.clients.message', $sel) }}" style="margin-top:8px">@csrf<textarea name="body" class="inp" rows="2" placeholder="مثلا: بضاعتك وصلت، نستنوك بعد الساعة 4" required></textarea><button class="btn wa sm mt">💬 أرسل</button></form>@endcan
        </div>
        <div class="sec" style="border-bottom:0">
            <h4>ملاحظات</h4>
            @can('customer-edit')
            <form method="post" action="{{ route('sg.clients.note', $sel) }}">
                @csrf
                <textarea name="notes" class="inp" rows="2" placeholder="مثلا: يفضل التسليم بعد الساعة 4 · مقاسه M">{{ $sel->notes }}</textarea>
                <div style="display:flex;gap:6px;margin-top:8px"><button class="btn sm">حفظ الملاحظة</button><button type="button" class="btn ghost sm" data-modal="editCust">تعديل البيانات</button><button type="button" class="btn ghost sm" data-modal="accCust">🔑 حسابه</button></div>
            </form>
            @else
                <div class="hint">{{ $sel->notes ?: '—' }}</div>
            @endcan
        </div>
    </div>
    @endif
</div>

@can('customer-create')
<div class="modal-bg" id="newCust"><div class="modal">
    <h3>عميل جديد</h3>
    <form method="post" action="{{ route('sg.clients.store') }}">
        @csrf
        <div class="f"><label class="l">الاسم</label><input name="name" class="inp" required></div>
        <div class="grid g2 f"><div><label class="l">الهاتف</label><input name="phone_1" id="ncPhone" class="inp numr" required></div><div><label class="l">هاتف آخر</label><input name="phone_2" class="inp numr"></div></div>
        <div class="f"><label class="l">العنوان / المدينة</label><input name="address" class="inp"></div>
        <div class="f"><label class="l">الكود <span class="note">(فارغ = آخر 4 أرقام من الهاتف)</span></label><input name="code" class="inp numr" data-code-check data-phone="#ncPhone" autocomplete="off"></div>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan
@if ($sel)
@can('customer-edit')
<div class="modal-bg" id="editCust"><div class="modal">
    <h3>تعديل {{ $sel->name }}</h3>
    <form method="post" action="{{ route('sg.clients.update', $sel) }}">
        @csrf @method('PUT')
        <div class="f"><label class="l">الاسم</label><input name="name" class="inp" value="{{ $sel->name }}" required></div>
        <div class="grid g2 f"><div><label class="l">الهاتف</label><input name="phone_1" class="inp numr" value="{{ $sel->phone_1 }}" required></div><div><label class="l">هاتف آخر</label><input name="phone_2" class="inp numr" value="{{ $sel->phone_2 }}"></div></div>
        <div class="f"><label class="l">العنوان / المدينة</label><input name="address" class="inp" value="{{ $sel->address }}"></div>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<div class="modal-bg" id="accCust"><div class="modal">
    <h3>دخول {{ $sel->name }} لحسابه</h3>
    <div class="hint mb">العميل يدخل من <b class="num" style="direction:ltr">{{ url('/login') }}</b> برقم هاتفه أو كوده وكلمة السر. {{ $sel->password ? '' : 'لم ليس لديه كلمة سر: أول مرة يدخل برقمه كلمة سر ويغيرها.' }}</div>
    <form method="post" action="{{ route('sg.clients.account', $sel) }}">
        @csrf @method('PUT')
        <div class="grid g2 f"><div><label class="l">رقم الدخول (الهاتف)</label><input name="phone_1" class="inp numr" value="{{ $sel->phone_1 }}" required></div><div><label class="l">الكود</label><input name="code" class="inp numr" value="{{ $sel->code }}" required data-code-check data-except="{{ $sel->id }}" autocomplete="off"></div></div>
        <div class="f"><label class="l">كلمة سر جديدة</label><input name="password" class="inp" placeholder="اتركه فارغاً لعدم التغيير" minlength="8"></div>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan
@endif
@include('sg.partials.code-check')
@role('Admin')
<div class="modal-bg" id="mTrust"><div class="modal" style="max-width:520px">
    <h3>✅ العملاء الموثوقين</h3>
    <div class="note mb">الموثوق يأكد طلب السلة من حسابه <b>بدون عربون</b> مهما كانت القيمة. الصق <b>أرقامهم أو أكوادهم</b> هني (كل واحد في سطر أو بفاصلة) ونعلموهم كلهم.</div>
    @php $trN = \Illuminate\Support\Facades\Schema::hasColumn('customers', 'trusted') ? \App\Models\Customer::withoutGlobalScopes()->where('trusted', true)->count() : 0; @endphp
    <div class="hint mb">عندك توا <b class="num">{{ $trN }}</b> عميل موثوق · تقدر تشيل أي واحد من صفحته</div>
    <form method="post" action="{{ route('sg.clients.trusted.chat') }}" enctype="multipart/form-data" class="hint mb" style="display:block">@csrf
        <b>📥 من شات قروب الحجز:</b> في الواتساب افتح القروب ← ⋮ ← المزيد ← <b>تصدير الدردشة</b> ← بدون وسائط، وارفع الملف هني. نطلعولك الأكواد اللي جنبها ✅ تراجعهم.
        <div style="display:flex;gap:8px;margin-top:8px"><input type="file" name="chat" accept=".txt,.zip,text/plain,application/zip" class="inp" required><button class="btn">📥 اقرا الشات</button></div>
    </form>
    <form method="post" action="{{ route('sg.clients.trusted.bulk') }}">@csrf
        <textarea name="phones" class="inp numr" rows="8" placeholder="0912345678&#10;4908&#10;+218 91 111 2222&#10;25598" required></textarea>
        <div style="display:flex;gap:8px;margin-top:10px"><button class="btn green">✅ علّمهم موثوقين</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endrole
@endsection
@push('script')
<script>
(function () {
  const all = document.getElementById('pickAll'), n = document.getElementById('pickN'); if (!all) return;
  const ps = () => [...document.querySelectorAll('.wpick')];
  const upd = () => n.textContent = ps().filter(p => p.checked).length;
  ps().forEach(p => p.addEventListener('change', upd));
  all.addEventListener('change', () => { ps().forEach(p => p.checked = all.checked); upd(); });
})();
</script>
@endpush
