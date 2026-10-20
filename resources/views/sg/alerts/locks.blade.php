@extends('sg.layout')
@section('title', 'حسابات مقفلة')
@section('content')
<div class="top"><div><h1>🔐 حسابات مقفلة</h1><div class="sub">الحسابات اللي فيها محاولات دخول غلط متكررة آخر 7 أيام — فك القفل وابعت للعميل إشعار</div></div>
    <div class="acts"><a class="btn ghost" href="{{ route('sg.alerts.center') }}">← مركز التنبيهات</a><button class="sg-burger">☰</button></div></div>
<div class="card mb"><h3>🔓 فك قفل يدوي</h3><div class="note">اكتب رقم هاتف العميل أو كوده (ينفع حتى لو تقفل قبل التحديث)</div>
<form method="post" action="{{ route('sg.locks.unlock') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:8px">@csrf
    <input name="login" class="inp numr" placeholder="09xxxxxxxx أو الكود" required style="max-width:220px">
    <select name="scope" class="pill"><option value="customer">👤 عميل</option><option value="admin">💼 موظف / مندوب</option></select>
    <label class="ck" style="margin:0"><input type="checkbox" name="reset" value="1"> رجّع كلمة السر لرقم هاتفه</label>
    <label class="ck" style="margin:0"><input type="checkbox" name="notify" value="1" checked> ابعتله إشعار</label>
    <button class="btn green">🔓 فك القفل</button></form></div>
<form method="post" action="{{ route('sg.locks.bulk') }}" id="lkB" class="card mb" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;padding:12px 16px">@csrf
    <label class="ck" style="margin:0;font-weight:700"><input type="checkbox" onchange="document.querySelectorAll('.lkC').forEach(c=>c.checked=this.checked)"> تحديد الكل</label>
    <label class="ck" style="margin:0"><input type="checkbox" name="notify" value="1" checked> ابعتلهم رسالة إن حسابهم انفك</label>
    <button class="btn green">🔓 فك القفل عن المحددين</button>
</form>
<div class="card tw"><div class="scroll-x">
<table class="t"><thead><tr><th style="width:30px"></th><th>الحساب</th><th>صاحبه</th><th>السبب</th><th>الحالة</th><th>الحل</th></tr></thead><tbody>
@foreach ($bans as $b)
    <tr>
        <td><input type="checkbox" class="lkC" name="items[]" value="cart:{{ $b->id }}" form="lkB" style="width:18px;height:18px"></td>
        <td><b class="num">{{ $b->phone_1 }}</b><div class="note">👤 عميل</div></td>
        <td class="cell2"><b>{{ $b->name }}</b><small class="num">كود {{ $b->code }}</small></td>
        <td>🛒 <b>كثرة السلات</b><div class="note">حسب أكثر من {{ \App\Http\Controllers\Portal\CartsController::SPAM_COUNT }} سلات في {{ \App\Http\Controllers\Portal\CartsController::SPAM_MINUTES }} دقايق</div></td>
        <td>@if ($b->cart_ban_until && now()->lt($b->cart_ban_until))<span class="chip d">موقوف · {{ now()->diffInMinutes($b->cart_ban_until) }} دقيقة</span>@else<span class="chip w">سلة وحدة كل ساعة لين {{ \Carbon\Carbon::parse($b->cart_probation_until)->format('j/n g:i a') }}</span>@endif</td>
        <td class="note">علّم عليه وفك من فوق</td>
    </tr>
@endforeach
@foreach ($rows as $x)
    <tr>
        <td><input type="checkbox" class="lkC" name="items[]" value="login:{{ $x['id'] }}" form="lkB" style="width:18px;height:18px"></td>
        <td><b class="num">{{ $x['login'] }}</b><div class="note">{{ $x['scope'] === 'admin' ? '💼 موظف / مندوب' : '👤 عميل' }}</div></td>
        <td class="cell2">@if ($x['customer'])<b>{{ $x['customer']->name }}</b><small class="num">كود {{ $x['customer']->code }} · {{ $x['customer']->phone_1 }}</small>
            @elseif ($x['user'])<b>{{ $x['user']->name }}</b>@else<span class="note" style="color:var(--down)">ما فيش حساب بهذا الرقم/الكود</span>@endif</td>
        <td>🔑 <b>دخول فاشل متكرر</b><div class="note num">{{ $x['times'] ?? 1 }} مرة · آخر محاولة {{ $x['at'] }} · IP {{ $x['ip'] }}</div></td>
        <td>@if ($x['left'])<span class="chip d">مقفل · {{ ceil($x['left'] / 60) }} دقيقة</span>@else<span class="chip w">انفك وحده — ممكن يعاود يغلط</span>@endif</td>
        <td><form method="post" action="{{ route('sg.locks.unlock') }}">@csrf<input type="hidden" name="id" value="{{ $x['id'] }}">
            @if ($x['customer'])
                <label class="ck" style="font-size:12.5px"><input type="checkbox" name="reset" value="1"> نسي كلمة السر؟ رجّعها لرقم هاتفه</label>
                <label class="ck" style="font-size:12.5px"><input type="checkbox" name="notify" value="1" checked> ابعتله إشعار</label>
            @endif
            <button class="btn ghost sm" style="margin-top:4px">🔓 فك هذا بس</button></form></td>
    </tr>
@endforeach
@if ($bans->isEmpty() && $rows->isEmpty())
    <tr><td colspan="6"><div class="empty"><span class="ic">🔐</span>ما فيش حسابات مقفلة ✓</div></td></tr>
@endif
</tbody></table></div></div>
@endsection
