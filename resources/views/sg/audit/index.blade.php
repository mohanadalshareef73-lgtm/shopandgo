@extends('sg.layout')
@section('title', 'سجل التعديلات')
@section('bodyClass', 'soft')
@php use App\Services\AuditService as AuS; use App\Models\AuditLog as AL; @endphp
@push('style')
<style>
.flt { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 14px 16px; border-bottom: 1px solid var(--line-soft); }
.flt select, .flt input { height: 38px; width: auto; min-width: 130px; }
.au td { vertical-align: top; }
.act { font-size: 12px; font-weight: 700; padding: 3px 9px; border-radius: 99px; white-space: nowrap; }
.act.created { background: var(--up-bg); color: var(--up); } .act.updated { background: var(--s1-bg); color: var(--s1); } .act.deleted { background: var(--down-bg); color: var(--down); }
.chg { display: flex; flex-wrap: wrap; gap: 4px 12px; font-size: 13px; }
.chg span { white-space: nowrap; } .chg i { font-style: normal; color: var(--ink-3); }
.chg del { color: var(--down); text-decoration: line-through; opacity: .8; } .chg ins { color: var(--up); text-decoration: none; font-weight: 700; }
.chg .sm { max-width: 520px; white-space: normal; color: var(--ink-2); }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>سجل التعديلات</h1><div class="sub">كل إضافة وتعديل ومسح في النظام: من داره، ومتى، وشن تبدل بالضبط</div></div>
    <div class="acts">@include('sg.partials.period')@if ($tab === 'audit')<a class="btn ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">⬇ Excel</a>@endif<button class="sg-burger">☰</button></div>
</div>
<nav class="tabs"><a href="{{ route('sg.audit', ['p' => request('p')]) }}" class="{{ $tab === 'audit' ? 'on' : '' }}">التعديلات</a><a href="{{ route('sg.audit', ['tab' => 'old', 'p' => request('p')]) }}" class="{{ $tab === 'old' ? 'on' : '' }}">السجل القديم</a></nav>

@if ($tab === 'audit')
<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">كل العمليات {{ $period->label }}</div><div class="v num">{{ $stats->sum() }}</div></div>
    <div class="card kpi green"><div class="lbl">إضافة</div><div class="v num">{{ $stats['created'] ?? 0 }}</div></div>
    <div class="card kpi"><div class="lbl">تعديل</div><div class="v num">{{ $stats['updated'] ?? 0 }}</div></div>
    <div class="card kpi red"><div class="lbl">مسح</div><div class="v num">{{ $stats['deleted'] ?? 0 }}</div><div class="s"><a href="{{ request()->fullUrlWithQuery(['action' => 'deleted']) }}" style="color:var(--s1)">اعرضهم فقط</a></div></div>
</div>
@endif

<div class="card tw">
    <form method="get" class="flt">
        <input type="hidden" name="p" value="{{ request('p', 'week') }}">@if ($tab === 'old')<input type="hidden" name="tab" value="old">@endif
        <div class="sx" style="flex:1;min-width:200px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" value="{{ request('q') }}" placeholder="رقم إيصال، اسم عميل، رقم…"></div>
        <select name="user" class="inp" data-autosubmit><option value="">كل الموظفين</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(request('user') == $u->id)>{{ $u->name }}</option>@endforeach @if ($tab === 'audit')<option value="system" @selected(request('user') === 'system')>النظام (أوتوماتيك)</option>@endif</select>
        @if ($tab === 'audit')
            <select name="model" class="inp" data-autosubmit><option value="">كل الأقسام</option>@foreach ($models as $k => $v)<option value="{{ $k }}" @selected(request('model') === $k)>{{ $v }}</option>@endforeach</select>
            <select name="action" class="inp" data-autosubmit><option value="">كل العمليات</option>@foreach (AL::ACTIONS as $k => $v)<option value="{{ $k }}" @selected(request('action') === $k)>{{ $v }}</option>@endforeach</select>
            <select name="field" class="inp" data-autosubmit><option value="">أي خانة</option>@foreach ($fields as $k => $v)<option value="{{ $k }}" @selected(request('field') === $k)>تعديل {{ $v }}</option>@endforeach</select>
            <label class="ck"><input type="checkbox" name="money" value="1" @checked(request('money')) data-autosubmit> المبلغ فقط</label>
        @else
            <select name="type" class="inp" data-autosubmit><option value="">كل الأنواع</option>@foreach ($types as $t)<option value="{{ $t }}" @selected(request('type') === $t)>{{ $t }}</option>@endforeach</select>
        @endif
        <button class="btn sm">بحث</button>
        @if (request()->hasAny(['q', 'user', 'model', 'action', 'field', 'money', 'type']))<a class="note" href="{{ route('sg.audit', ['tab' => $tab === 'old' ? 'old' : null, 'p' => request('p')]) }}">✕ مسح الفلاتر</a>@endif
    </form>
    <div class="scroll-x">
    <table class="t au">
        @if ($tab === 'audit')
        <thead><tr><th>الوقت</th><th>من</th><th>العملية</th><th>على</th><th>ماذا تبدل</th></tr></thead>
        <tbody>
        @forelse ($rows as $a)
            @php $link = AuS::link($a); @endphp
            <tr>
                <td class="note num" style="white-space:nowrap">{{ $a->created_at->format('Y-m-d') }}<br>{{ $a->created_at->format('g:i:s a') }}</td>
                <td><b style="font-size:13.5px">{{ $a->user_name ?: '—' }}</b>@if ($a->ip)<div class="note num">{{ $a->ip }}</div>@endif</td>
                <td><span class="act {{ $a->action }}">{{ AL::ACTIONS[$a->action] ?? $a->action }}</span></td>
                <td style="white-space:nowrap"><span class="note">{{ AuS::modelLabel($a->model) }}</span><br>@if ($link)<a class="code" href="{{ $link }}">{{ $a->label ?: '#' . $a->model_id }}</a>@else<b class="num">{{ $a->label ?: '#' . $a->model_id }}</b>@endif</td>
                <td><div class="chg">
                    @foreach ((array) $a->changes as $k => $v)
                        @if (is_array($v))
                            <span><i>{{ AuS::fieldLabel($k) }}:</i> <del>{{ $v[0] === '' || $v[0] === null ? 'فارغ' : AuS::val($v[0]) }}</del> ← <ins>{{ $v[1] === '' || $v[1] === null ? 'فارغ' : AuS::val($v[1]) }}</ins></span>
                        @elseif (!in_array($k, AuS::HIDE, true))
                            <span class="sm"><i>{{ AuS::fieldLabel($k) }}:</i> {{ \Illuminate\Support\Str::limit(AuS::val($v), 60) }}</span>
                        @endif
                    @endforeach
                </div></td>
            </tr>
        @empty
            <tr><td colspan="5"><div class="empty"><span class="ic">🕓</span>لا يوجد تعديلات بالفلاتر هذه</div></td></tr>
        @endforelse
        </tbody>
        @else
        <thead><tr><th>الوقت</th><th>من</th><th>النوع</th><th>العنوان</th><th>التفاصيل</th></tr></thead>
        <tbody>
        @forelse ($rows as $l)
            <tr><td class="note num" style="white-space:nowrap">{{ $l->created_at->format('Y-m-d g:i a') }}</td><td>{{ optional($l->user)->name }}</td><td><span class="chip n">{{ $l->type }}</span></td><td>{{ $l->title }}</td><td class="note wrap">{{ $l->log }}</td></tr>
        @empty
            <tr><td colspan="5"><div class="empty"><span class="ic">🕓</span>لا يوجد سجلات</div></td></tr>
        @endforelse
        </tbody>
        @endif
    </table>
    </div>
    <div class="tw-f">@include('sg.partials.pager', ['p' => $rows])</div>
</div>
@if ($tab === 'audit' && $byUser->count())
<div class="card mt" style="max-width:520px">
    <h3>أكثر الناس شغل {{ $period->label }}</h3>
    @foreach ($byUser as $name => $n)<div class="ln"><span>{{ $name ?: '—' }}</span><b class="num">{{ $n }}</b></div>@endforeach
</div>
@endif
@endsection
