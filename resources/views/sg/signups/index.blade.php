@extends('sg.layout')
@section('title', 'طلبات التسجيل')
@section('bodyClass', 'soft')
@section('content')
<div class="crumb"><a href="{{ route('sg.clients') }}">العملاء</a> › طلبات التسجيل</div>
<div class="top"><div><h1>طلبات تسجيل العملاء</h1><div class="sub">عملاء أنشأوا حساباً بأنفسهم · لا يظهرون في النظام ولا يستطيعون الدخول إلا بعد موافقتك</div></div><div class="acts">@can('system-edit')<a class="btn ghost" href="{{ route('sg.settings', ['tab' => 'delivery']) }}">⚙ إعدادات التسجيل</a>@endcan<button class="sg-burger">☰</button></div></div>
<div class="stages">
    <a href="{{ route('sg.signups') }}" class="stg {{ $status === 'pending' ? 'on' : '' }}">بانتظار الموافقة<span class="n">{{ $counts['pending'] }}</span></a>
    <a href="{{ route('sg.signups', ['status' => 'rejected']) }}" class="stg {{ $status === 'rejected' ? 'on' : '' }}">مرفوضة<span class="n">{{ $counts['rejected'] }}</span></a>
</div>
@forelse ($rows as $c)
    <div class="card mb">
        <div style="display:flex;gap:14px;align-items:flex-start;flex-wrap:wrap">
            <span class="av" style="width:44px;height:44px">{{ mb_substr($c->name, 0, 1) }}</span>
            <div style="flex:1;min-width:260px">
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b style="font-size:16px">{{ $c->name }}</b>
                    @if ($c->phone_verified_at)<span class="chip u">✓ الرقم مُتحقَّق منه عبر واتساب</span>@else<span class="chip w">⚠ الرقم غير مُتحقَّق منه</span>@endif
                    <span class="note">{{ optional($c->signup_at)->diffForHumans() }}</span></div>
                <div class="grid g3" style="margin-top:10px;gap:8px">
                    <div class="hint" style="margin:0"><small class="note">الهاتف</small><div class="num"><a href="https://wa.me/{{ \App\Services\MessageService::normalize((string) $c->phone_1) }}" target="_blank">{{ $c->phone_1 }}</a>{{ $c->phone_2 ? ' · ' . $c->phone_2 : '' }}</div></div>
                    <div class="hint" style="margin:0"><small class="note">العنوان</small><div>{{ $c->address }}</div></div>
                    <div class="hint" style="margin:0"><small class="note">عنوان IP</small><div class="num">{{ $c->signup_ip ?: '—' }}</div></div>
                </div>
                @if (($similar[$c->id] ?? collect())->count())
                    <div class="warnbox" style="margin-top:8px">⚠ يشبه عملاء موجودين: @foreach ($similar[$c->id] as $x)<a href="{{ route('sg.clients', ['id' => $x->id]) }}" style="text-decoration:underline">{{ $x->name }} ({{ $x->code }})</a>@if (!$loop->last)، @endif @endforeach</div>
                @endif
                @if ($c->signup_note)<div class="note" style="margin-top:8px">سبب الرفض: {{ $c->signup_note }}</div>@endif
            </div>
            <div style="width:330px;max-width:100%">
                <form method="post" action="{{ route('sg.signups.approve', $c->id) }}" class="mb">
                    @csrf
                    <label class="l">كود العميل</label>
                    <div style="display:flex;gap:8px"><input name="code" class="inp numr" value="{{ $c->code }}" required data-code-check style="flex:1"><button class="btn green">✓ موافقة</button></div>
                    <label class="ck" style="margin-top:6px"><input type="checkbox" name="send_whatsapp" value="1" checked> إبلاغ العميل عبر واتساب بالكود</label>
                </form>
                @if ($status === 'pending')
                <details><summary class="note" style="cursor:pointer">رفض الطلب…</summary>
                    <form method="post" action="{{ route('sg.signups.reject', $c->id) }}" style="margin-top:8px" data-confirm="رفض طلب {{ $c->name }}؟">
                        @csrf
                        <input name="reason" class="inp" placeholder="السبب (اختياري، يُرسل للعميل)">
                        <label class="ck" style="margin-top:6px"><input type="checkbox" name="send_whatsapp" value="1"> إبلاغ العميل عبر واتساب</label>
                        <button class="btn sm" style="margin-top:6px;background:var(--down);color:#fff">رفض</button>
                    </form>
                </details>
                @else
                <form method="post" action="{{ route('sg.signups.destroy', $c->id) }}" data-confirm="حذف الطلب نهائياً؟ سيتمكن صاحب الرقم من التسجيل من جديد">@csrf @method('DELETE')<button class="btn ghost sm">حذف نهائياً</button></form>
                @endif
            </div>
        </div>
    </div>
@empty
    <div class="card"><div class="empty"><span class="ic">👤</span>{{ $status === 'pending' ? 'لا توجد طلبات تسجيل جديدة' : 'لا توجد طلبات مرفوضة' }}</div></div>
@endforelse
@include('sg.partials.pager', ['p' => $rows])
@include('sg.partials.code-check')
@endsection
