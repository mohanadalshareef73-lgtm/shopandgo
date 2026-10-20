<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#262c4a">
<title>{{ $title ?? 'سلاتي' }}</title>
<link rel="stylesheet" href="{{ asset('assets/sg/app.css') }}?v=6">
<style>
body { background: #f2f3f7; margin: 0; font-family: Somar, sans-serif; color: #1b1f33; }
.wrap { max-width: 720px; margin: 0 auto; padding: 16px 16px 120px; }
.hd { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.hd a { color: #262c4a; text-decoration: none; font-weight: 700; }
.card { background: #fff; border-radius: 18px; padding: 16px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
.inp { width: 100%; box-sizing: border-box; border: 1.5px solid #dfe2ea; border-radius: 12px; padding: 12px 14px; font: inherit; font-size: 15px; background: #fff; }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; background: #262c4a; color: #fff; border: 0; border-radius: 12px; padding: 12px 18px; font: inherit; font-weight: 700; font-size: 15px; cursor: pointer; text-decoration: none; }
.btn.green { background: #1d8a5f; } .btn.ghost { background: #fff; color: #262c4a; border: 1.5px solid #dfe2ea; } .btn.block { width: 100%; }
.note { color: #6b7086; font-size: 13px; }
.flash { padding: 12px 14px; border-radius: 12px; margin-bottom: 12px; font-size: 14px; }
.flash.s { background: #e6f6ee; color: #0f6b4c; } .flash.e { background: #fdecea; color: #b3261e; } .flash.w { background: #fff6e0; color: #8a5a00; }
.num { font-family: InterNum, Somar; direction: ltr; unicode-bidi: isolate; }
</style>
</head>
<body><div class="wrap">
@include('partials.back', ['home' => route('customer.dashboard'), 'float' => true])
<div class="hd"><b style="font-size:19px">{{ $title ?? 'سلاتي' }}</b>@auth('customer')<a href="{{ route('customer.dashboard') }}">← حسابي</a>@else<a href="{{ route('customer.login') }}">🔑 دخول</a>@endauth</div>
@foreach (['success' => 's', 'error' => 'e', 'warning' => 'w'] as $k => $cl)@if (session($k))<div class="flash {{ $cl }}">{{ session($k) }}</div>@endif @endforeach
@if ($errors->any())<div class="flash e">{{ $errors->first() }}</div>@endif
