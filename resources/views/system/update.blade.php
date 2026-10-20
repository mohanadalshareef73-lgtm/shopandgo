<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>تحديث النظام · Shop & Go</title>
<style>
:root{--bg:#f4f5fa;--card:#fff;--ink:#1d2033;--mut:#6b7090;--line:#e6e8f0;--brand:#2b2f7a;--up:#1f9d55;--down:#d93838;--warn:#b7791f}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font-family:Tahoma,Arial,sans-serif;font-size:15px;line-height:1.7}
.w{max-width:760px;margin:0 auto;padding:24px 16px}h1{font-size:22px;margin:0 0 4px}.mut{color:var(--mut);font-size:13.5px}
.c{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:18px;margin-top:14px}
.ok{color:var(--up);font-weight:700}.bad{color:var(--down);font-weight:700}
ol{margin:6px 0;padding-inline-start:20px}code,pre{direction:ltr;text-align:left;font-family:Consolas,monospace;font-size:12.5px}
pre{background:#11142a;color:#e8eaf6;border-radius:10px;padding:12px;white-space:pre-wrap;word-break:break-word;max-height:420px;overflow:auto}
.btn{display:inline-block;border:0;border-radius:10px;background:var(--brand);color:#fff;font-size:16px;font-weight:700;padding:12px 22px;cursor:pointer;text-decoration:none}
.btn.g{background:var(--up)}.btn.s{background:#eef0f8;color:var(--ink);font-size:13px;padding:7px 12px}
label{display:flex;gap:8px;align-items:center;margin:10px 0;font-size:14px}
.row{display:flex;justify-content:space-between;gap:8px;align-items:center;border-top:1px solid var(--line);padding:8px 0}
</style>
</head>
<body><div class="w">
<h1>تحديث النظام</h1>
<div class="mut">هذه الصفحة تأخذ نسخة احتياطية من قاعدة البيانات أولاً، ثم تحدّث الجداول. لا تُحذف أي بيانات.</div>

@if ($out)<div class="c"><b>النتيجة</b><pre>{{ $out }}</pre>@if (!count($pending))<a class="btn g" href="{{ url('/admin') }}">افتح المنظومة ←</a>@endif</div>@endif

<div class="c">
    @if (count($pending))
        <div class="bad">يوجد {{ count($pending) }} تحديث لقاعدة البيانات لم يُنفَّذ بعد</div>
        <ol>@foreach ($pending as $p)<li><code>{{ $p }}</code></li>@endforeach</ol>
        <form method="post" action="{{ route('system.update.run') }}" onsubmit="this.querySelector('button').disabled=true;this.querySelector('button').textContent='جارٍ التحديث… لا تغلق الصفحة'">
            @csrf
            @if (!$zip)<div class="bad">⚠ امتداد php-zip غير مركب، فالنسخة الاحتياطية التلقائية لن تعمل. خذ نسخة من phpMyAdmin (Export) ثم فعّل الخيار تحت.</div>@endif
            <label><input type="checkbox" name="skip_backup" value="1"> أخذت نسخة من قاعدة البيانات بنفسي (استعمله فقط إذا فشلت النسخة التلقائية)</label>
            <button class="btn">🔒 خذ نسخة احتياطية ثم حدّث</button>
        </form>
    @else
        <div class="ok">✓ قاعدة البيانات محدّثة — لا يوجد شيء للتنفيذ</div>
        <a class="btn g" href="{{ url('/admin') }}" style="margin-top:8px">افتح المنظومة ←</a>
    @endif
</div>

<div class="c">
    <b>آخر النسخ الاحتياطية</b> <span class="mut">(نزّل آخر نسخة واحفظها في جهازك)</span>
    @forelse ($backups as $b)
        <div class="row"><span><code>{{ $b['name'] }}</code> <span class="mut">{{ round($b['size'] / 1048576, 1) }} MB · {{ $b['time']->format('Y-m-d g:i a') }}</span></span><a class="btn s" href="{{ route('system.update.download', $b['name']) }}">⬇ تنزيل</a></div>
    @empty
        <div class="mut">لا توجد نسخ بعد</div>
    @endforelse
</div>

<div class="c mut">
    <b style="color:var(--ink)">لو صار خطأ بعد التحديث:</b>
    <ol>
        <li>cPanel ← phpMyAdmin ← اختر قاعدة البيانات ← Import ← ارفع ملف <code>.sql</code> الموجود داخل النسخة الاحتياطية.</li>
        <li>cPanel ← File Manager ← ارجع ملفات المنظومة القديمة من النسخة التي أخذتها قبل الرفع.</li>
    </ol>
</div>
</div></body></html>
