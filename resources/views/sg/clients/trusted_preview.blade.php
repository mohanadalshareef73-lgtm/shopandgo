@extends('sg.layout')
@section('title', 'الموثوقين من الشات')
@section('content')
<div class="top"><div><h1>✅ الموثوقين من شات قروب الحجز</h1><div class="sub">لقينا {{ count($rows) }} عميل جنب كوده علامة ✅ — شيل العلامة على اللي ما تبيهوش وأكد</div></div>
    <div class="acts"><a class="btn ghost" href="{{ route('sg.clients') }}">← العملاء</a><button class="sg-burger">☰</button></div></div>
@if ($unknown)<div class="hint mb">⚠ فيه {{ $unknown }} رقم/كود جنبه ✅ ما لقيناهش في المنظومة (ممكن تواريخ أو أرقام طلبات) — ما يتعلّموش.</div>@endif
<form method="post" action="{{ route('sg.clients.trusted.bulk') }}" id="trF">@csrf
<input type="hidden" name="phones" id="trP">
<div class="card tw"><div class="scroll-x">
<table class="t"><thead><tr><th style="width:30px"><input type="checkbox" checked onchange="document.querySelectorAll('.trC').forEach(c=>c.checked=this.checked)"></th><th>العميل</th><th>الكود</th><th class="n">مرات ✅</th><th>الحالة</th></tr></thead><tbody>
@forelse ($rows as $r)
    <tr>
        <td><input type="checkbox" class="trC" value="{{ $r['c']->code ?: $r['c']->phone_1 }}" {{ $r['c']->trusted ? '' : 'checked' }} style="width:18px;height:18px"></td>
        <td class="cell2"><b>{{ $r['c']->name }}</b><small class="num">{{ $r['c']->phone_1 }}</small></td>
        <td class="num"><b>{{ $r['c']->code }}</b></td>
        <td class="n num">{{ $r['times'] }}</td>
        <td>@if ($r['c']->trusted)<span class="chip u">✅ موثوق من قبل</span>@else<span class="note">جديد</span>@endif</td>
    </tr>
@empty
    <tr><td colspan="5"><div class="empty"><span class="ic">✅</span>ما لقيناش أكواد جنبها علامة ✅ في الملف</div></td></tr>
@endforelse
</tbody></table></div></div>
@if (count($rows))<button class="btn green" style="margin-top:12px" onclick="const v=[...document.querySelectorAll('.trC:checked')].map(c=>c.value);if(!v.length){alert('ما علّمتش حتى عميل');return false;}document.getElementById('trP').value=v.join('\n');">✅ علّم المحددين موثوقين</button>@endif
</form>
@endsection
