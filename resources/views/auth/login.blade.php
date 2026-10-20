<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>تسجيل الدخول · Shop & Go</title>
<link rel="icon" href="{{ asset('assets/sg/logo.png') }}">
<link rel="stylesheet" href="{{ asset('assets/sg/sg.css') }}?v=6">
<style>
body { margin: 0; background: #f4f5f8; min-height: 100vh; display: grid; grid-template-columns: 1fr 560px; }
.brand { background: #262c4a; color: #fff; padding: 56px 64px; display: flex; flex-direction: column; position: relative; overflow: hidden; }
.brand .lg { width: 190px; background: #fff; border-radius: 16px; padding: 10px 14px; position: relative; }
.brand h2 { font-size: 34px; margin: auto 0 12px; line-height: 1.4; position: relative; }
.brand p { color: #c9cde0; font-size: 16px; line-height: 1.9; margin: 0 0 28px; max-width: 520px; position: relative; }
.brand .who { display: flex; gap: 10px; flex-wrap: wrap; position: relative; }
.brand .who span { background: rgba(255,255,255,.1); border-radius: 99px; padding: 8px 14px; font-size: 14px; }
.brand .ring { position: absolute; left: -140px; top: -140px; width: 420px; height: 420px; border-radius: 50%; border: 60px solid rgba(255,255,255,.04); }
.brand .ring2 { position: absolute; left: 120px; bottom: -200px; width: 380px; height: 380px; border-radius: 50%; background: rgba(98,80,214,.18); }
.brand .foot { margin-top: 40px; font-size: 13px; color: #8a90a3; position: relative; }
.form { display: flex; flex-direction: column; justify-content: center; padding: 40px 70px; background: #fff; }
.form h1 { font-size: 28px; margin: 0 0 6px; }
.form .sub { color: var(--ink-3); font-size: 15px; margin-bottom: 30px; }
.f2 { margin-bottom: 18px; } .f2 label { display: block; font-weight: 700; font-size: 14px; margin-bottom: 8px; }
.box { height: 54px; border: 1.5px solid var(--line); border-radius: 14px; display: flex; align-items: center; gap: 10px; padding: 0 16px; background: #fff; }
.box:focus-within { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(38,44,74,.08); }
.box input { flex: 1; border: 0; outline: 0; font: inherit; font-size: 15.5px; background: none; height: 100%; }
.box .eye { cursor: pointer; color: var(--ink-3); user-select: none; }
.row2 { display: flex; justify-content: space-between; align-items: center; margin: 4px 0 24px; font-size: 14px; }
.row2 label { display: inline-flex; align-items: center; gap: 8px; } .row2 input { width: 18px; height: 18px; accent-color: var(--brand); }
.go { height: 56px; border-radius: 14px; background: var(--brand); color: #fff; font-weight: 700; font-size: 17px; border: 0; width: 100%; cursor: pointer; font-family: inherit; }
.sec2 { margin-top: 24px; display: flex; gap: 10px; background: var(--line-soft); border-radius: 14px; padding: 12px 14px; font-size: 13.5px; color: var(--ink-2); line-height: 1.7; }
.err { background: var(--down-bg); color: var(--down); border-radius: 12px; padding: 11px 14px; margin-bottom: 16px; font-size: 14px; }
@media (max-width: 900px) { body { grid-template-columns: 1fr; } .brand { padding: 28px 24px; } .brand h2 { margin-top: 26px; font-size: 24px; } .brand .ring, .brand .ring2, .brand .foot { display: none; } .form { padding: 28px 22px; } }
</style>
</head>
<body>
<div class="brand"><div class="ring"></div><div class="ring2"></div>
    <img class="lg" src="{{ asset('assets/sg/logo.png') }}" alt="Shop & Go">
    <h2>منظومة Shop &amp; Go<br>لإدارة الطلبات والشحن</h2>
    <p>الطلبات، والرحلات، والتسليم، والمندوبون، والحسابات… في مكان واحد.</p>
    <div class="who"><span>👤 الإدارة</span><span>🧑‍💼 الموظفون</span><span>🛵 المندوبون</span></div>
    <div class="foot">© {{ date('Y') }} Shop &amp; Go</div>
</div>
<form class="form" method="post" action="{{ route('admin.login') }}">
    @csrf
    <h1>تسجيل الدخول</h1>
    <div class="sub">هذه الصفحة مخصصة لفريق العمل فقط</div>
    @if (session('error'))<div class="err" style="font-size:16px;line-height:1.7;padding:14px 16px;font-weight:700">{{ session('error') }}</div>@endif
    @if (session('warning'))<div class="err" style="background:#fff6e0;color:#8a5a00;font-size:16px;line-height:1.7;padding:14px 16px;font-weight:700">{{ session('warning') }}</div>@endif
    @if ($errors->any())<div class="err">{{ $errors->first() }}</div>@endif
    <div class="f2"><label>البريد الإلكتروني أو اسم المستخدم</label><div class="box"><span>✉</span><input name="name" value="{{ old('name') }}" required autofocus autocomplete="username" style="direction:ltr;text-align:right"></div></div>
    <div class="f2"><label>كلمة المرور</label><div class="box"><span>🔒</span><input type="password" name="password" id="pw" required autocomplete="current-password" style="direction:ltr;text-align:right"><span class="eye" onclick="const p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'">👁</span></div></div>
    <div class="row2"><label><input type="checkbox" name="remember" value="1" checked> تذكرني على هذا الجهاز</label><span class="note">نسيت كلمة المرور؟ تواصل مع الإدارة</span></div>
    <button class="go">دخول</button>
    <div class="sec2">🛡 بعد الدخول يُنقل كل مستخدم إلى واجهته: الإدارة والموظفون إلى لوحة التحكم، والمندوب إلى تطبيق المندوب.</div>
</form>
</body>
</html>
