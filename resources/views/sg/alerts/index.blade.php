@extends('sg.layout')
@section('title', 'مركز التنبيهات')
@section('bodyClass', 'soft')
@php use App\Http\Controllers\Sg\AlertsController as AC; @endphp
@section('content')
<div class="top"><div><h1>مركز التنبيهات</h1><div class="sub">كل التنبيهات مفلترة حسب القسم · {{ $period->label }}</div></div>
    <div class="acts">@include('sg.partials.period')<form method="post" action="{{ route('sg.alerts.center.read') }}">@csrf<input type="hidden" name="g" value="{{ $g }}"><button class="btn ghost">✓ علّم {{ $g ? 'القسم' : 'الكل' }} مقروءاً</button></form><form method="post" action="{{ route('sg.alerts.clear') }}">@csrf<input type="hidden" name="g" value="{{ $g }}"><button class="btn ghost" style="color:var(--down)" data-confirm="نمسحو كل التنبيهات المقروءة؟">🗑 امسح المقروءة</button></form><button class="sg-burger">☰</button></div></div>

<div class="card mb">
    <h3>الحالة الآن</h3>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:8px">
        @foreach ($now as [$lbl, $n, $url])
            <a href="{{ $url }}" class="hint" style="margin:0;text-decoration:none;color:inherit;{{ $n ? 'border:1px solid #f0c674;background:var(--warn-bg)' : '' }}"><div style="font-size:13px">{{ $lbl }}</div><b class="num" style="font-size:22px;color:{{ $n ? 'var(--warn)' : 'var(--up)' }}">{{ $n ?: '✓' }}</b></a>
        @endforeach
    </div>
</div>

@php $lockN = count(\App\Services\LoginGuard::list()) + (\Illuminate\Support\Facades\Schema::hasColumn('customers', 'cart_ban_until') ? \App\Models\Customer::withoutGlobalScopes()->where(fn ($q) => $q->where('cart_ban_until', '>', now())->orWhere('cart_probation_until', '>', now()))->count() : 0); @endphp
@if ($lockN)<a href="{{ route('sg.locks') }}" class="flash err" style="display:block;text-decoration:none;margin-bottom:10px">🔐 فيه <b>{{ $lockN }}</b> حساب مقفل (دخول فاشل أو كثرة سلات) — اضغط هنا وشوف السبب وفك القفل</a>
@else<div style="margin-bottom:8px"><a href="{{ route('sg.locks') }}" class="note">🔐 الحسابات المقفلة</a></div>@endif

@role('Admin')
@php $mOff = \App\Services\AppSettings::mobileOff(); @endphp
<form method="post" action="{{ route('sg.alerts.mobile') }}" class="card mb" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;{{ $mOff ? 'border:2px solid #e03131;background:#fdecea' : 'border:2px solid #2f9e44' }}">@csrf
    <div style="flex:1;min-width:220px"><b style="font-size:16px">{{ $mOff ? '🔕 إشعارات الجوال موقفة' : '🔔 إشعارات الجوال شغالة' }}</b>
        <div class="note">{{ $mOff ? 'ما يجيك حتى إشعار على التلفون ولا واتساب (تنبيهات، ورديات، تقارير، نسخ احتياطية) — كلها هنا في المنظومة بس' : 'التنبيهات والتقارير توصلك على الواتساب وعلى شاشة التلفون' }}</div></div>
    <input type="hidden" name="off" value="{{ $mOff ? 0 : 1 }}">
    <button class="btn {{ $mOff ? 'green' : '' }}" style="{{ $mOff ? '' : 'background:#e03131;color:#fff' }};font-size:15px">{{ $mOff ? '🔔 شغّلها' : '🔕 وقّف إشعارات الجوال' }}</button>
</form>
@php $wp = \App\Services\AppSettings::reportPhones(); $mute = (array) \App\Services\AppSettings::get('wa_mute', []); @endphp
<details class="card mb"><summary style="cursor:pointer;font-weight:700">📱 رسائل واتساب اللي تجي لأرقام الإدارة — وقّف أو شغّل كل نوع لكل رقم</summary>
@if (!$wp)<div class="note" style="margin-top:8px">ما فيش أرقام إدارة — زيدهم من الإعدادات.</div>@else
<form method="post" action="{{ route('sg.alerts.wa') }}" style="margin-top:10px">@csrf
    <label class="ck"><input type="checkbox" name="alerts_on" value="1" @checked(\App\Services\AppSettings::get('courier_alert_whatsapp'))> <b>تشغيل تنبيهات واتساب أصلاً</b> (لو مقفولة ما يجي حتى تنبيه — التقارير والنسخ تمشي بإعداداتها)</label>
    <div class="scroll-x"><table class="t"><thead><tr><th>نوع الرسالة</th>@foreach ($wp as $ph)<th class="num" style="text-align:center">{{ $ph }}</th>@endforeach</tr></thead><tbody>
    @foreach (\App\Services\AppSettings::WA_KINDS as $k => $lbl)
        <tr><td>{{ $lbl }}</td>@foreach ($wp as $ph)<td style="text-align:center"><input type="checkbox" name="on[{{ $ph }}][]" value="{{ $k }}" @checked(!in_array($k, (array) ($mute[$ph] ?? []), true))></td>@endforeach</tr>
    @endforeach
    </tbody></table></div>
    <button class="btn sm" style="margin-top:8px">💾 حفظ</button></form>@endif
</details>
@endrole

<div class="stages">
    <a href="{{ request()->fullUrlWithQuery(['g' => null, 'page' => null]) }}" class="stg {{ !$g ? 'on' : '' }}">الكل<span class="n">{{ collect($counts)->sum('all') }}</span></a>
    @foreach (AC::GROUPS as $k => $grp)
        <a href="{{ request()->fullUrlWithQuery(['g' => $k, 'page' => null]) }}" class="stg {{ $g === $k ? 'on' : '' }}">{{ $grp[1] }} {{ $grp[0] }}<span class="n">{{ $counts[$k]['all'] }}</span>@if ($counts[$k]['unread'])<span class="chip d" style="margin-inline-start:4px">{{ $counts[$k]['unread'] }}</span>@endif</a>
    @endforeach
</div>
<div class="card tw">
    <form class="tw-h" method="get" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
        <input type="hidden" name="p" value="{{ $period->preset }}">@if ($g)<input type="hidden" name="g" value="{{ $g }}">@endif
        <div class="sx" style="flex:1;min-width:200px"><input name="q" value="{{ request('q') }}" placeholder="بحث في التنبيهات…"></div>
        <select name="level" class="pill" data-autosubmit><option value="">كل المستويات</option><option value="warn" @selected(request('level') === 'warn')>⚠ تحذير</option><option value="info" @selected(request('level') === 'info')>ℹ معلومة</option></select>
        <label class="ck" style="margin:0"><input type="checkbox" name="unread" value="1" @checked(request('unread')) data-autosubmit> غير المقروءة فقط</label>
    </form>
    <form method="post" action="{{ route('sg.alerts.bulk') }}" id="bulkF" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;padding:10px 16px;border-bottom:1px solid var(--line-soft);background:var(--line-soft)">@csrf
        <label class="ck" style="margin:0;font-weight:700"><input type="checkbox" id="bulkAll" onchange="document.querySelectorAll('.bulkC').forEach(c=>c.checked=this.checked);bulkN()"> تحديد الكل</label>
        <span class="note" id="bulkCnt">0 محدد</span>
        <button name="do" value="read" class="btn ghost sm" id="bulkR" disabled>✓ علّمهم مقروء</button>
        <button name="do" value="delete" class="btn ghost sm" id="bulkD" disabled style="color:var(--down)" onclick="return confirm('نمسحو التنبيهات المحددة؟')">🗑 حذف المحدد</button>
    </form>
    @forelse ($rows as $a)
        <div style="display:flex;flex-direction:row;gap:12px;border-bottom:1px solid var(--line-soft);padding:12px 16px;align-items:flex-start;{{ $a->read_at ? '' : 'background:#fbfbff' }}">
            <input type="checkbox" class="bulkC" name="ids[]" value="{{ $a->id }}" form="bulkF" onchange="bulkN()" style="width:18px;height:18px;margin-top:3px;flex:none">
            <span class="chip {{ $a->level === 'warn' ? 'w' : 'n' }}">{{ AC::GROUPS[AC::groupOf($a->type)][1] }} {{ AC::GROUPS[AC::groupOf($a->type)][0] }}</span>
            <div style="flex:1"><b style="font-size:14px">{{ $a->title }}</b>@if (!$a->read_at) <span class="chip d" style="font-size:11px">جديد</span>@endif
                @if ($a->body)<div style="font-size:13px;color:var(--ink-2);white-space:pre-line">{{ $a->body }}</div>@endif
                <div class="note" style="font-size:12px">{{ $a->created_at->format('Y-m-d g:i a') }} · {{ $a->created_at->diffForHumans() }}</div></div>
            <div style="display:flex;gap:6px">@if ($a->url)<a class="btn ghost sm" href="{{ route('sg.alerts.open', $a) }}">فتح</a>@endif
                @if (!$a->read_at)<form method="post" action="{{ route('sg.alerts.center.read') }}">@csrf<input type="hidden" name="id" value="{{ $a->id }}"><button class="btn ghost sm" title="مقروء">✓</button></form>@endif
                <form method="post" action="{{ route('sg.alerts.destroy', $a) }}">@csrf @method('DELETE')<button class="btn ghost sm" title="مسح" style="color:var(--down)">🗑</button></form></div>
        </div>
    @empty
        <div class="empty"><span class="ic">🔔</span>لا توجد تنبيهات هنا</div>
    @endforelse
</div>
@include('sg.partials.pager', ['p' => $rows])
<script>
function bulkN(){const n=document.querySelectorAll('.bulkC:checked').length,t=document.querySelectorAll('.bulkC').length;document.getElementById('bulkCnt').textContent=n+' محدد';document.getElementById('bulkR').disabled=document.getElementById('bulkD').disabled=!n;const a=document.getElementById('bulkAll');a.checked=n&&n===t;a.indeterminate=n>0&&n<t;}
</script>
@endsection
