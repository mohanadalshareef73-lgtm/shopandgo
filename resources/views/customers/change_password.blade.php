<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#262c4a">
<title>اختيار كلمة مرور جديدة</title>
<link rel="stylesheet" href="{{ asset('assets/sg/app.css') }}?v=6">
</head>
<body>
<div class="app" style="padding-bottom:0">
<div class="body" style="padding-top:40px">
    <img src="{{ asset('assets/sg/logo.png') }}" style="width:150px;display:block;margin:0 auto 20px" alt="">
    <h1 style="text-align:center;font-size:22px;margin:0 0 6px">اختر كلمة مرور جديدة</h1>
    <p style="text-align:center;color:var(--ink-3);font-size:14px;margin:0 0 22px">لحماية حسابك، اختر كلمة مرور خاصة بك بدلاً من رقم الهاتف</p>
    @if (session('warning'))<div class="flash warn">{{ session('warning') }}</div>@endif
    @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('customer.change.password') }}" class="card" style="padding:18px">
        @csrf
        <div class="fl"><label>كلمة المرور الجديدة (8 أحرف على الأقل)</label><input type="password" name="new_password" class="inp" style="height:52px" required minlength="8"></div>
        <div class="fl"><label>تأكيد كلمة المرور</label><input type="password" name="new_confirm_password" class="inp" style="height:52px" required></div>
        <button class="btn" style="width:100%;height:52px">حفظ والمتابعة</button>
    </form>
</div>
</div>
</body>
</html>
