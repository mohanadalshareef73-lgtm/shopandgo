@php $s = \App\Services\AppSettings::all(); @endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8">

<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#262c4a">
<title>تسجيل الدخول · {{ $s['shop_name'] }}</title>
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
.box input::placeholder { color: #b9bdc9; font-size: 14px; font-weight: 400; }
.sub2 { color: #9aa0ae; font-size: 12px; margin-top: 6px; line-height: 1.7; }
.otp { display: inline-block; background: #1d8a5f; color: #fff; padding: 9px 14px; border-radius: 10px; font-weight: 700; border: 0; font-family: inherit; font-size: 14px; cursor: pointer; }
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
    <div class="top2"><div class="c"></div><img class="lg" src="{{ asset('assets/sg/logo.png') }}" alt=""><h1>مرحباً بك 👋</h1><p>تابع طلباتك وشحناتك، واطلب التوصيل، واطّلع على حسابك.</p></div>
    <form class="card2" method="post" action="{{ route('customer.login.submit') }}">
        @csrf
        @if (session('error'))<div class="flash err">{{ session('error') }}
            @if (session('no_account'))<div style="margin-top:8px"><a href="{{ route('customer.register') }}" style="display:inline-block;background:#1d8a5f;color:#fff;padding:8px 14px;border-radius:10px;text-decoration:none;font-weight:700">📝 اعمل حساب جديد</a></div>@endif
            @if (session('wrong_pw'))<div style="margin-top:8px"><div style="margin-bottom:6px">تقدر تدخل برمز يوصلك على الواتساب، وبعدها تختار كلمة مرور جديدة 👇</div><button class="otp" formaction="{{ route('customer.forgot.send') }}" formnovalidate>📲 ادخل برمز على الواتساب</button></div>@endif
        </div>@endif
        @if (session('success'))<div class="flash ok">{{ session('success') }}</div>@endif
        @if (session('warning'))<div class="flash warn">{{ session('warning') }}</div>@endif
        @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
        <div class="f2"><label>رقم الهاتف أو كود العميل</label><div class="box"><span>📱</span><input name="phone_1" value="{{ old('phone_1') }}" inputmode="tel" required autocomplete="username" placeholder="مثال: 0912345678"></div></div>
        <div class="f2"><label>كلمة المرور</label><div class="box"><span>🔒</span><input type="password" name="password" id="pw" required autocomplete="current-password" placeholder="كلمة المرور متاعك"><span style="cursor:pointer;color:var(--ink-3)" onclick="const p=document.getElementById('pw');p.type=p.type==='password'?'text':'password'">👁</span></div>
            <div class="sub2">أول مرة؟ كلمة المرور هي رقم هاتفك، ويجيك رمز على الواتساب، وبعدها تختار كلمة مرور جديدة وتدخل بيها ديما · نسيتها؟ تقدر تدخل برمز على الواتساب</div></div>
        <button class="go">دخول</button>
        @if ($s['signup_enabled'] ?? true)<a class="go" href="{{ route('customer.register') }}" style="display:grid;place-items:center;background:#fff;color:var(--brand);border:1.5px solid var(--line);margin-top:12px;text-decoration:none">ليس لديك حساب؟ أنشئ حساباً جديداً</a>@endif
    </form>
    <div class="links"><a href="{{ route('customer.forgot') }}" style="font-weight:700">🔑 نسيت كلمة المرور؟ غيّرها برمز على الواتساب</a><br>أو @if ($s['shop_phone'])<a href="https://wa.me/{{ \App\Services\MessageService::normalize((string) $s['shop_phone']) }}" target="_blank">تواصل معنا عبر واتساب</a>@else<b>تواصل معنا</b>@endif</div>
    <div class="feat"><div><span>📦</span>طلباتي</div><div><span>🚚</span>طلب توصيل</div><div><span>💳</span>حسابي</div></div>
    @include('portal.partials.trust')
</div>
</body>
</html>
