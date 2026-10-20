@php $s = \App\Services\AppSettings::all(); @endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#262c4a">
<title>نسيت كلمة المرور · {{ $s['shop_name'] }}</title>
<link rel="icon" href="{{ asset('assets/sg/logo.png') }}">
<link rel="stylesheet" href="{{ asset('assets/sg/app.css') }}?v=6">
<style>
.top2 { background: #262c4a; height: 300px; border-radius: 0 0 34px 34px; color: #fff; padding: 54px 26px 0; position: relative; overflow: hidden; }
.top2 .lg { width: 150px; background: #fff; border-radius: 14px; padding: 8px 12px; position: relative; }
.top2 h1 { font-size: 25px; margin: 26px 0 6px; position: relative; } .top2 p { color: #c9cde0; font-size: 14.5px; margin: 0; line-height: 1.8; position: relative; }
.top2 .c { position: absolute; left: -60px; top: -60px; width: 200px; height: 200px; border-radius: 50%; background: rgba(255,255,255,.05); }
.card2 { background: #fff; margin: -46px 18px 0; border-radius: 24px; padding: 22px 20px; position: relative; box-shadow: 0 10px 30px rgba(22,26,46,.08); }
.f2 { margin-bottom: 16px; } .f2 label { display: block; font-weight: 700; font-size: 13.5px; margin-bottom: 7px; }
.box { height: 52px; border: 1.5px solid var(--line); border-radius: 14px; display: flex; align-items: center; gap: 10px; padding: 0 14px; background: #fff; }
.box:focus-within { border-color: var(--brand); box-shadow: 0 0 0 4px rgba(38,44,74,.08); }
.box input { flex: 1; border: 0; outline: 0; font: inherit; font-size: 16px; background: none; height: 100%; font-family: InterNum, Somar; direction: ltr; text-align: right; }
.go { height: 54px; border-radius: 14px; background: var(--brand); color: #fff; font-weight: 700; font-size: 16px; border: 0; width: 100%; margin-top: 6px; font-family: inherit; cursor: pointer; }
.hint2 { background: var(--s1-bg); color: #1d4f8f; border-radius: 14px; padding: 11px 13px; font-size: 12.5px; line-height: 1.7; margin-top: 14px; }
.links { text-align: center; margin-top: 18px; font-size: 13.5px; color: var(--ink-3); } .links a { color: var(--wa); font-weight: 700; }
.feat { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin: 18px 18px 30px; }
.feat div { background: #fff; border-radius: 14px; padding: 10px 6px; text-align: center; font-size: 11.5px; color: var(--ink-2); } .feat div span { display: block; font-size: 20px; margin-bottom: 4px; }
</style>
</head>
<body>
<div class="app" style="padding-bottom:0">
    <div class="top2"><div class="c"></div><img class="lg" src="{{ asset('assets/sg/logo.png') }}" alt=""><h1>نسيت كلمة المرور؟ 🔑</h1><p>اكتب رقم هاتفك، ونبعتولك رمز على الواتساب تغير بيه كلمة المرور</p></div>
    <form class="card2" method="post" action="{{ route('customer.forgot.send') }}">
        @csrf
        @if (session('error'))<div class="flash err">{{ session('error') }}
            @if (session('no_account'))<div style="margin-top:8px"><a href="{{ route('customer.register') }}" style="display:inline-block;background:#1d8a5f;color:#fff;padding:8px 14px;border-radius:10px;text-decoration:none;font-weight:700">📝 اعمل حساب جديد</a></div>@endif
        </div>@endif
        @if (session('warning'))<div class="flash warn">{{ session('warning') }}</div>@endif
        @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
        <div class="f2"><label>رقم الهاتف أو كود العميل</label><div class="box"><span>📱</span><input name="phone_1" value="{{ old('phone_1') }}" inputmode="tel" required autofocus></div></div>
        <button class="go">📲 ابعتلي الرمز على الواتساب</button>
        <div class="hint2">الرمز يوصلك على رقم الواتساب المسجل في حسابك، وصالح 10 دقايق.</div>
    </form>
    <div class="links"><a href="{{ route('customer.login') }}">← رجوع لتسجيل الدخول</a></div>
</div>
</body>
</html>
