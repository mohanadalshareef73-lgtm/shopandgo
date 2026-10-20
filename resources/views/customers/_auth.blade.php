<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#262c4a">
<title>@yield('t') · {{ $s['shop_name'] }}</title>
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
.top2.sm { height: auto; min-height: 250px; padding: 40px 26px 66px; }
.f2 small { display: block; color: var(--ink-3); font-size: 12px; margin-top: 5px; }
.box textarea, .box select { flex: 1; border: 0; outline: 0; font: inherit; font-size: 15px; background: none; }
.agree { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; line-height: 1.7; color: var(--ink-2); margin: 4px 0 12px; }
.agree input { width: 20px; height: 20px; margin-top: 2px; accent-color: var(--brand); }
.hp { position: absolute !important; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); opacity: 0; pointer-events: none; }
.otp { display: flex; gap: 8px; direction: ltr; justify-content: center; margin: 10px 0 18px; }
.otp input { width: 46px; height: 56px; border: 1.5px solid var(--line); border-radius: 12px; text-align: center; font-family: InterNum; font-size: 24px; font-weight: 700; }
.otp input:focus { border-color: var(--brand); outline: 0; box-shadow: 0 0 0 4px rgba(38,44,74,.08); }
.steps { display: flex; gap: 6px; margin: 0 0 16px; } .steps i { flex: 1; height: 5px; border-radius: 3px; background: var(--line); } .steps i.on { background: var(--brand); }
.pwm { height: 5px; border-radius: 3px; background: var(--line); margin-top: 7px; overflow: hidden; } .pwm b { display: block; height: 100%; width: 0; transition: .2s; }
</style>
</head>
<body>
<div class="app" style="padding-bottom:30px">
    @yield('body')
</div>
@stack('js')
</body>
</html>
