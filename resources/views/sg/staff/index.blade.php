@extends('sg.layout')
@section('title', 'الموظفين')
@php
    use App\Services\TreasuryService as TS; use App\Http\Controllers\Sg\StaffController as SC;
    $f = fn ($n) => TS::fmt($n);
    $palette = ['#262c4a', '#2a78d6', '#6250d6', '#0f8a5f', '#eb6834', '#b5480f'];
    $roleOf = function ($u) { if ((int) $u->type_id === 1 || $u->hasRole('Admin')) return 'مدير'; return optional($u->roles->first())->name ?: 'موظف'; };
    $working = $stats->filter(fn ($s) => $s['online'])->count();
    $cashHeld = $stats->sum(fn ($s) => $s['shift']['shift'] ? $s['shift']['expected'] : 0);
@endphp
@push('style')
<style>
.staff-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; margin-bottom: 14px; }
.sc { display: block; background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 14px; }
.sc.on { border: 2px solid var(--brand); }
.sc.off { opacity: .55; }
.sc .hd { display: flex; gap: 10px; align-items: center; }
.sc .dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.sc .g { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; margin-top: 12px; }
.sc .g div { background: var(--line-soft); border-radius: 9px; padding: 6px 8px; font-size: 12px; color: var(--ink-3); }
.sc .g b { display: block; font-size: 16px; color: var(--ink); }
.feed .it { display: flex; gap: 10px; padding: 8px 0; border-bottom: 1px solid var(--line-soft); font-size: 13.5px; }
.feed .it .note { margin-inline-start: auto; white-space: nowrap; }
.roles { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 12px; }
.roles button { border: 1px solid var(--line); background: #fff; border-radius: 12px; padding: 10px; text-align: right; font: inherit; cursor: pointer; }
.roles button.on { border: 2px solid var(--brand); background: #f6f7fb; }
.roles small { display: block; font-size: 12px; color: var(--ink-3); }
table.perm { width: 100%; border-collapse: collapse; }
table.perm th { font-size: 12.5px; color: var(--ink-3); font-weight: 400; padding: 6px; text-align: center; border-bottom: 1px solid var(--line); }
table.perm th:first-child, table.perm td:first-child { text-align: right; }
table.perm td { padding: 8px 6px; border-bottom: 1px solid var(--line-soft); text-align: center; font-size: 14px; }
table.perm td small { display: block; font-size: 11.5px; color: var(--ink-3); }
.shiftc .count { background: rgba(255,255,255,.08); border-radius: 12px; padding: 12px; margin-top: 12px; }
.mini { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 14px; }
.mini .card small { display: block; font-size: 12.5px; color: var(--ink-3); } .mini .card b { font-size: 22px; }
.ptabs { display: flex; gap: 4px; border-bottom: 1px solid var(--line); margin-bottom: 14px; }
.ptabs a { padding: 10px 14px; border-bottom: 3px solid transparent; margin-bottom: -1px; color: var(--ink-2); }
.ptabs a.on { border-color: var(--brand); color: var(--ink); font-weight: 700; }
@media (max-width: 900px) { .roles { grid-template-columns: 1fr 1fr; } }
</style>
@endpush
@section('content')
@if (!$sel)
<div class="top">
    <div><h1>الموظفين</h1><div class="sub">كل موظف لديه حسابه، وكل حركة تُسجَّل باسمه</div></div>
    <div class="acts">@include('sg.partials.period')@can('user-create')<button class="btn" data-modal="mNew">+ موظف جديد</button>@endcan<button class="sg-burger">☰</button></div>
</div>
<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">موظفين شغالين الآن</div><div class="v"><span class="num">{{ $working }}</span> <small>من {{ $users->where('status', true)->count() }}</small></div><div class="s">فاتحين الحساب آخر 10 دقايق</div></div>
    <div class="card kpi"><div class="lbl">إيصالات {{ $period->label }} (الكل)</div><div class="v num">{{ $ordersAll }}</div></div>
    <div class="card kpi"><div class="lbl">نقداً عند الموظفين (لا تُقفل)</div><div class="v"><span class="num">{{ $f($cashHeld) }}</span> <small>د.ل</small></div><div class="s">{{ $openShifts }} وردية مفتوحة</div></div>
    <div class="card kpi"><div class="lbl">ورديات تقفلت اليوم</div><div class="v num">{{ $closedToday }}</div></div>
</div>
<div class="staff-grid">
    @foreach ($users as $u)
        @php $s = $stats[$u->id]; @endphp
        <a class="sc {{ $u->status ? '' : 'off' }}" href="?id={{ $u->id }}">
            <div class="hd"><span class="av" style="background:{{ $palette[$u->id % 6] }}">{{ mb_substr($u->name, 0, 1) }}</span><div style="flex:1"><b>{{ $u->name }}</b><div class="note">{{ $roleOf($u) }}{{ $u->status ? '' : ' · موقوف' }}</div></div><span class="dot" style="background:{{ $s['online'] ? '#1baf7a' : '#c9cdd8' }}" title="{{ $s['online'] ? 'شغال الآن' : ($u->last_seen_at ? 'آخر ظهور ' . $u->last_seen_at->diffForHumans() : '') }}"></span></div>
            <div class="g"><div>إيصالات<b class="num">{{ $s['orders'] }}</b></div><div>سلّم<b class="num">{{ $s['delivered'] }}</b></div><div>قبض<b class="num">{{ $f($s['collected']) }}</b></div></div>
            <div class="note" style="margin-top:8px">{{ $s['shift']['shift'] ? 'وردية مفتوحة · معه ' . $f($s['shift']['expected']) . ' د.ل' : 'لا يوجد وردية مفتوحة' }}</div>
        </a>
    @endforeach
</div>
<div class="side-lay">
    <div class="card tw">
        <div class="tw-h"><h3>شغل الموظفين {{ $period->label }} <span class="h-note">من دار شن</span></h3></div>
        <table class="t"><thead><tr><th>الموظف</th><th class="n">إيصالات</th><th class="n">استلام بضاعة</th><th class="n">تسليم</th><th class="n">قبض</th><th>الوردية</th></tr></thead><tbody>
        @foreach ($users->where('status', true)->sortByDesc(fn ($u) => $stats[$u->id]['orders'] + $stats[$u->id]['delivered']) as $u)
            @php $s = $stats[$u->id]; @endphp
            <tr><td><a href="?id={{ $u->id }}"><b>{{ $u->name }}</b></a></td><td class="n num">{{ $s['orders'] }}</td><td class="n num">{{ $s['received'] }}</td><td class="n num">{{ $s['delivered'] }}</td><td class="n num plus">{{ $f($s['collected']) }}</td><td>@if ($s['shift']['shift'])<span class="chip w">مفتوحة · {{ $f($s['shift']['expected']) }}</span>@if (auth()->id() === $u->id || auth()->user()->can('user-edit'))<form method="post" action="{{ route('sg.finance.close') }}" data-confirm="نقفلو يومية {{ $u->name }} ونستلمو منه {{ $f($s['shift']['expected']) }} د.ل؟" style="display:inline-flex;gap:4px;margin-inline-start:6px;vertical-align:middle">@csrf<input type="hidden" name="user_id" value="{{ $u->id }}"><input name="counted" class="inp numr" style="width:80px;padding:4px 6px" value="{{ round($s['shift']['expected'], 2) }}" required title="كم سلّم فعلياً؟"><button class="btn green sm">🔒 إغلاق يومية</button></form>@endif @else<span class="chip n">—</span>@endif</td></tr>
        @endforeach
        </tbody></table>
    </div>
    <div class="card feed">
        <h3>آخر حركات <span class="h-note">مباشر</span></h3>
        @foreach ($feed as $l)
            <div class="it"><span class="av" style="width:26px;height:26px;font-size:11px;background:{{ $palette[($l->user_id ?? 0) % 6] }}">{{ mb_substr(optional($l->user)->name ?? '؟', 0, 1) }}</span><div><b>{{ optional($l->user)->name }}</b> · {{ trim($l->title) }}<div class="note" style="margin:0">{{ \Illuminate\Support\Str::limit(trim($l->log), 70) }}</div></div><span class="note">{{ $l->created_at->diffForHumans(null, true) }}</span></div>
        @endforeach
    </div>
</div>

@can('user-create')
<div class="modal-bg" id="mNew"><div class="modal">
    <h3>موظف جديد</h3>
    <form method="post" action="{{ route('sg.staff.store') }}">
        @csrf
        <div class="f"><label class="l">الاسم (يدخل به)</label><input name="name" class="inp" required value="{{ old('name') }}"></div>
        <div class="grid g2 f"><div><label class="l">الهاتف</label><input name="phone" class="inp numr" value="{{ old('phone') }}"></div><div><label class="l">الإيميل (اختياري)</label><input name="email" class="inp" value="{{ old('email') }}"></div></div>
        <div class="f"><label class="l">كلمة المرور</label><input name="password" class="inp" required minlength="8"></div>
        <div class="f"><label class="l">الدور</label><div class="opts">@foreach (SC::PRESETS as $k => $pr)<label><input type="radio" name="preset" value="{{ $k }}" @checked($k === 'front')><span>{{ $pr[0] }}</span></label>@endforeach</div><div class="note mt">يمكنك أن تعدل الصلاحيات بالتفصيل لاحقاً</div></div>
        <div style="display:flex;gap:8px"><button class="btn">إضافة</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan

@else
@php
    $tab = request('t', 'perm');
    $s = $stats[$sel->id] ?? ['orders' => 0, 'delivered' => 0, 'received' => 0, 'collected' => 0, 'online' => false];
    $sh = $prof['shift'];
@endphp
<div class="crumb"><a href="{{ route('sg.staff') }}">الموظفين</a> › {{ $sel->name }}</div>
<div class="top">
    <div style="display:flex;gap:14px;align-items:center">
        <span class="av" style="width:58px;height:58px;font-size:24px;background:{{ $palette[$sel->id % 6] }}">{{ mb_substr($sel->name, 0, 1) }}</span>
        <div><h1>{{ $sel->name }}</h1><div style="margin:6px 0 8px;display:inline-flex;flex-wrap:wrap;align-items:center;gap:8px 14px;background:#eef6ff;border:1.5px solid #cfe0f7;border-radius:12px;padding:10px 16px;font-size:16px;color:#1d3f6e;letter-spacing:.2px">
            <span>🔑 <b>يدخل بـ:</b> <b class="num" style="font-size:18px;letter-spacing:.5px">{{ $sel->name }}</b>@if ($sel->phone) <span style="opacity:.8">أو رقمه</span> <b class="num" style="font-size:17px">{{ $sel->phone }}</b>@endif</span>
            <span style="font-size:14.5px">🌐 من <b class="num" dir="ltr">{{ url('/admin/login') }}</b></span></div><div class="sub"><span class="chip v">{{ $roleOf($sel) }}</span> <span class="num">{{ $sel->phone }}</span> · {{ $s['online'] ? 'شغال الآن' : ($sel->last_seen_at ? 'آخر ظهور ' . $sel->last_seen_at->diffForHumans() : 'لم يدخل لا يزال') }} · موظف من {{ \App\Services\Period::MONTHS[$sel->created_at->month - 1] }} {{ $sel->created_at->year }} @if (!$sel->status)<span class="chip d">موقوف</span>@endif</div></div>
    </div>
    <div class="acts">
        @can('user-edit')
            <button class="btn ghost" data-modal="mPass">تغيير كلمة المرور</button>
            <button class="btn" data-modal="mEdit">✏️ تعديل البيانات</button>
            @if ($sel->id !== auth()->id() && (int) $sel->type_id !== 1)<form method="post" action="{{ route('sg.staff.toggle', $sel) }}" data-confirm="متأكد؟">@csrf<button class="btn {{ $sel->status ? 'red' : 'green' }}">{{ $sel->status ? 'إيقاف الحساب' : 'تفعيل الحساب' }}</button></form>@endif
            @if ($sel->id !== auth()->id() && (int) $sel->type_id !== 1)<form method="post" action="{{ route('sg.staff.destroy', $sel) }}" data-confirm="نحذفو {{ $sel->name }}؟ ما عاد يقدر يدخل للمنظومة">@csrf @method('DELETE')<button class="btn ghost" style="color:var(--down)">🗑 حذف</button></form>@endif
        @endcan
        <button class="sg-burger">☰</button>
    </div>
</div>
<div class="ptabs"><a href="?id={{ $sel->id }}&t=perm" class="{{ $tab === 'perm' ? 'on' : '' }}">الصلاحيات والوردية</a><a href="?id={{ $sel->id }}&t=log" class="{{ $tab === 'log' ? 'on' : '' }}">سجل الحركات</a></div>

@if ($tab === 'perm')
<div class="side-lay" style="grid-template-columns:1fr 380px">
    <div class="card">
        @if ($prof['isAdmin'])
            <h3>الصلاحيات</h3>
            <div class="hint">🔒 {{ $sel->name }} مدير — لديه كل الصلاحيات</div>
            @if ((int) $sel->type_id !== 1)@can('user-edit')<form method="post" action="{{ route('sg.staff.perms', $sel) }}" class="mt">@csrf<input type="hidden" name="preset" value="front"><button class="btn ghost sm">حوله لدور استقبال وتسليم</button></form>@endcan @endif
        @else
            <h3>ماذا يستطيع أن يدير {{ $sel->name }}؟ <span class="h-note">اختر دور جاهز وعدّل عليه</span></h3>
            @can('user-edit')
            <div class="roles">
                @foreach (SC::PRESETS as $k => $pr)
                    <form method="post" action="{{ route('sg.staff.perms', $sel) }}" style="display:contents" @if ($k === 'admin') data-confirm="منحه كل الصلاحيات؟" @endif>@csrf<input type="hidden" name="preset" value="{{ $k }}"><button class="{{ $prof['preset'] === $k ? 'on' : '' }}"><b>{{ $pr[0] }}</b><small>{{ $pr[1] }}</small></button></form>
                @endforeach
            </div>
            @endcan
            <form method="post" action="{{ route('sg.staff.perms', $sel) }}">
                @csrf
                <table class="perm"><thead><tr><th>القسم</th><th>يرى</th><th>يضيف</th><th>يعدّل</th><th>يحذف</th></tr></thead><tbody>
                @foreach (SC::SECTIONS as $k => $sec)
                    <tr><td>{{ $sec[0] }}@if ($sec[1])<small>{{ $sec[1] }}</small>@endif</td>
                    @foreach ([0, 1, 2, 3] as $i)
                        <td>@if ($sec[2 + $i])<label class="tgl"><input type="checkbox" name="p[{{ $k }}][{{ $i }}]" value="1" @checked(in_array($sec[2 + $i], $prof['perms']))><span class="tg"></span></label>@else<span class="note">—</span>@endif</td>
                    @endforeach
                    </tr>
                @endforeach
                </tbody></table>
                @can('user-edit')<div style="margin-top:12px;display:flex;justify-content:space-between;align-items:center"><span class="note">🔒 كل حركة يديرها {{ $sel->name }} تُسجَّل باسمه</span><button class="btn">حفظ الصلاحيات</button></div>@endcan
            </form>
        @endif
    </div>
    <div>
        <div class="card dark shiftc">
            <h3>الوردية <span class="h-note" style="color:#c9cde0">{{ $sh['shift'] ? 'من ' . $sh['since']->format('g:i A') : 'لا يوجد وردية مفتوحة' }}</span></h3>
            <div class="ln"><span>استلم في البداية</span><b class="num">{{ $f($sh['opening']) }}</b></div>
            <div class="ln"><span>+ قبض من العملاء (نقداً)</span><b class="num" style="color:#5fd3a3">+{{ $f($sh['cash_in']) }}</b></div>
            <div class="ln"><span>− صرف</span><b class="num" style="color:#ff8a80">−{{ $f($sh['cash_out']) }}</b></div>
            <div class="ln"><span>المصرف (للعلم، غير في يده)</span><b class="num">{{ $f($sh['other_in']) }}</b></div>
            <div class="ln tot"><span style="color:#fff">يجب أن يسلّم نقداً</span><b><span class="num">{{ $f($sh['expected']) }}</span> د.ل</b></div>
            @if ($sh['shift'] && (auth()->id() === $sel->id || auth()->user()->can('user-edit')))
            <form class="count" method="post" action="{{ route('sg.finance.close') }}" data-confirm="نقفلو الوردية؟">
                @csrf <input type="hidden" name="user_id" value="{{ $sel->id }}">
                <label class="l" style="color:#c9cde0">كم سلّم فعلياً؟</label>
                <div style="display:flex;gap:8px"><input name="counted" class="inp numr" value="{{ round($sh['expected'], 2) }}" required><button class="btn green">تقفيل الوردية</button></div>
                <div class="note" style="color:#c9cde0;margin-top:8px">يتبعث تقرير الوردية للمدير على واتساب</div>
            </form>
            @endif
        </div>
        <div class="mini">
            <div class="card"><small>إيصالات سجلها الشهر</small><b class="num">{{ $prof['monthOrders'] }}</b></div>
            <div class="card"><small>طلبيات سلّمها الشهر</small><b class="num">{{ $prof['monthDelivered'] }}</b></div>
            <div class="card"><small>قبض {{ $period->label }}</small><b class="num">{{ $f($s['collected']) }}</b></div>
            <div class="card"><small>متوسط الوردية (الشهر)</small><b class="num">{{ $f($prof['avgShift']) }}</b></div>
        </div>
        @if (auth()->user()->hasRole('Admin') && \App\Support\Fast::hasColumn('purchases', 'staff_id'))
        @php
            $pay = \App\Models\Purchase::where('staff_id', $sel->id)->whereIn('staff_kind', ['salary', 'advance'])->orderByDesc('date')->orderByDesc('id')->take(24)->get();
            $openAdvN = (float) \App\Models\Purchase::where('staff_id', $sel->id)->where('staff_kind', 'advance')->whereNull('settled_id')->sum('cost');
            $yearSal = (float) \App\Models\Purchase::where('staff_id', $sel->id)->where('date', '>=', now()->startOfYear()->toDateString())->sum('cost');
        @endphp
        <div class="card mt">
            <h3>💰 المرتبات والسلف <span class="h-note">السنة هذي {{ $f($yearSal) }} د.ل</span></h3>
            @if ($openAdvN > 0)<div class="hint" style="background:#fff4e6;color:#c2410c;font-weight:800">⚠ سلف ما تخصمتش: {{ $f($openAdvN) }} د.ل</div>@endif
            @forelse ($pay as $e)
                <div class="ln"><span>{{ $e->staff_kind === 'salary' ? '💰 مرتب' : '💸 سلفة' }} · <span class="num">{{ $e->date }}</span>@if ($e->staff_kind === 'advance') <span class="chip {{ $e->settled_id ? 'u' : 'w' }}" style="font-size:11px">{{ $e->settled_id ? 'تخصمت' : 'مفتوحة' }}</span>@endif<small style="display:block">{{ $e->note }}</small></span><b class="num">{{ $f($e->cost) }}</b></div>
            @empty
                <div class="note">ما فيش مرتبات ولا سلف مسجلة — سجلها من <a href="{{ route('sg.expenses') }}" style="text-decoration:underline">المصروفات</a></div>
            @endforelse
        </div>
        @endif
        @if ($prof['shifts']->count())
        <div class="card mt">
            <h3>آخر التقفيلات</h3>
            @foreach ($prof['shifts'] as $x)
                <div class="ln" style="font-size:13.5px"><span>{{ $x->closed_at->format('j/n g:i A') }}</span><span><b class="num">{{ $f($x->counted_cash) }}</b> @if (abs($x->diff) >= 0.01)<span class="chip d num">{{ $f($x->diff) }}</span>@else<span class="chip u">✓</span>@endif</span></div>
            @endforeach
        </div>
        @endif
    </div>
</div>
@else
<div class="card tw">
    <table class="t"><thead><tr><th>الوقت</th><th>العملية</th><th>التفاصيل</th></tr></thead><tbody>
    @forelse ($prof['log'] as $l)
        <tr><td class="note num">{{ $l->created_at->format('Y-m-d g:i A') }}</td><td><b>{{ trim($l->title) }}</b></td><td class="note wrap">{{ trim($l->log) }}</td></tr>
    @empty
        <tr><td colspan="3"><div class="empty">لا يوجد حركات</div></td></tr>
    @endforelse
    </tbody></table>
    <div class="tw-f">@include('sg.partials.pager', ['p' => $prof['log']])</div>
</div>
@endif

@can('user-edit')
<div class="modal-bg" id="mPass"><div class="modal">
    <h3>كلمة سر جديدة لـ {{ $sel->name }}</h3>
    <form method="post" action="{{ route('sg.staff.password', $sel) }}">@csrf
        <div class="f"><input name="password" class="inp" required minlength="8"></div>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<div class="modal-bg" id="mEdit"><div class="modal">
    <h3>تعديل {{ $sel->name }}</h3>
    <form method="post" action="{{ route('sg.staff.update', $sel) }}">@csrf @method('PUT')
        <div class="f"><label class="l">الاسم</label><input name="name" class="inp" value="{{ $sel->name }}" required></div>
        <div class="f"><label class="l">الإيميل <span class="note">(اختياري)</span></label><input name="email" class="inp" value="{{ str_ends_with((string) $sel->email, '@staff.local') ? '' : $sel->email }}"></div>
        <div class="f"><label class="l">الهاتف</label><input name="phone" class="inp numr" value="{{ $sel->phone }}"></div>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan
@endif
@endsection
