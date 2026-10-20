@extends('sg.layout')
@section('title', 'النسخ الاحتياطي')
@section('bodyClass', 'soft')
@php $days = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت']; $last = $list[0] ?? null; @endphp
@section('content')
<div class="top">
    <div><h1>النسخ الاحتياطي</h1><div class="sub">نسخة كاملة من المنظومة كل أسبوع، يصلك على إيميلك، ويمكنك تنزيلها في أي وقت</div></div>
    <div class="acts">@role('Admin')<form method="post" action="{{ route('sg.cleanup') }}">@csrf<button class="btn ghost" title="يمسح ملفات PDF وصور المنتجات القديمة والسجلات الكبيرة (يصير وحده كل ليلة 4:30)">🧹 نظّف المساحة توا</button></form>@endrole
        @role('Admin')<a class="btn ghost" href="{{ route('system.update') }}">⟳ تحديث النظام</a>@endrole
        <form method="post" action="{{ route('sg.backups.store') }}">@csrf<input type="hidden" name="send" value="1"><button class="btn">🔒 خذ نسخة الآن وابعثها</button></form>
        <button class="sg-burger">☰</button>
    </div>
</div>
@if (!$zip)<div class="hint mb" style="background:var(--down-bg);color:var(--down)">⚠ امتداد php-zip غير مركب على السيرفر — المبرمج يجب أن يثبّته لكي النسخ يعمل</div>@endif

<div class="kpis g3">
    <div class="card kpi {{ $last && $last['time']->gt(now()->subDays(8)) ? 'green' : 'red' }}"><div class="lbl">آخر نسخة</div><div class="v" style="font-size:22px">{{ $last ? $last['time']->diffForHumans() : 'لا يوجد' }}</div><div class="s">{{ $last ? $last['time']->format('Y-m-d g:i a') : 'خذ نسخة الآن' }}</div></div>
    <div class="card kpi"><div class="lbl">النسخة القادمة</div><div class="v" style="font-size:22px">{{ $s['backup_enabled'] ? 'يوم ' . $days[(int) $s['backup_day']] : 'مقفولة' }}</div><div class="s">{{ $s['backup_enabled'] ? 'الساعة ' . $s['backup_time'] : '' }}</div></div>
    <div class="card kpi"><div class="lbl">تمشي لـ</div><div class="v" style="font-size:18px;direction:ltr;text-align:right">{{ $s['backup_email'] ?: '—' }}</div><div class="s">{{ $s['backup_email'] ? 'إيميل' : 'اكتب إيميلك تحت' }}</div></div>
</div>

<div class="side-lay">
<div class="card tw">
    <div class="tw-h"><h3>النسخ المحفوظة على السيرفر</h3><span class="note">آخر {{ $s['backup_keep'] }} نسخ</span></div>
    <table class="t">
        <thead><tr><th>النسخة</th><th>التاريخ</th><th class="n">الحجم</th><th></th></tr></thead>
        <tbody>
        @forelse ($list as $b)
            <tr><td class="num">{{ $b['name'] }}</td><td class="note">{{ $b['time']->format('Y-m-d g:i a') }} · {{ $b['time']->diffForHumans() }}</td><td class="n num">{{ number_format($b['size'] / 1048576, 1) }} MB</td><td><a class="btn ghost sm" href="{{ route('sg.backups.download', $b['name']) }}">⬇ تنزيل</a></td></tr>
        @empty
            <tr><td colspan="4"><div class="empty"><span class="ic">🔒</span>لا يزال لا يوجد نسخ — اضغط "خذ نسخة الآن"</div></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
<form class="card sticky" method="post" action="{{ route('sg.settings.save') }}">
    @csrf
    <h3>الإعدادات</h3>
    <label class="tgl" style="display:flex;margin-bottom:10px"><input type="checkbox" name="backup_enabled" value="1" @checked($s['backup_enabled'])><span class="tg"></span><span>نسخة أوتوماتيك كل أسبوع</span></label><input type="hidden" name="_bools[]" value="backup_enabled">
    <div class="f"><label class="l">إيميلك (يصلك عليه النسخة)</label><input type="email" name="backup_email" class="inp" style="direction:ltr" value="{{ $s['backup_email'] }}" placeholder="name@gmail.com"></div>
    <div class="grid g2 f"><div><label class="l">اليوم</label><select name="backup_day" class="inp">@foreach ($days as $i => $d)<option value="{{ $i }}" @selected((int) $s['backup_day'] === $i)>{{ $d }}</option>@endforeach</select></div><div><label class="l">الساعة</label><input type="time" name="backup_time" class="inp" value="{{ $s['backup_time'] }}"></div></div>
    <div class="f"><label class="l">كم نسخة نُبقي على السيرفر</label><input name="backup_keep" class="inp numr" value="{{ $s['backup_keep'] }}"></div>
    <label class="tgl" style="display:flex;margin-bottom:10px"><input type="checkbox" name="backup_files" value="1" @checked($s['backup_files'])><span class="tg"></span><span>إضافة الصور والإيصالات المرفوعة</span></label><input type="hidden" name="_bools[]" value="backup_files">
    <label class="tgl" style="display:flex;margin-bottom:10px"><input type="checkbox" name="backup_whatsapp" value="1" @checked($s['backup_whatsapp'])><span class="tg"></span><span>أرسل لي رسالة واتساب لما النسخة تجهز</span></label><input type="hidden" name="_bools[]" value="backup_whatsapp">
    <button class="btn block">حفظ</button>
    <div class="note mt">النسخة التي أكبر من 20MB لا تُرسل مرفق في الإيميل، يصلك رابط تنزيلها منه.</div>
</form>
</div>
@endsection
