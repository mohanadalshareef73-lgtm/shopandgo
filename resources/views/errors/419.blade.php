<!doctype html>
<html lang="ar" dir="rtl">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>الصفحة قديمة</title>
<style>
body { margin: 0; min-height: 100vh; display: grid; place-items: center; background: #f4f5f9; font-family: system-ui, Tahoma, sans-serif; color: #000; }
.b { background: #fff; border-radius: 18px; padding: 28px 24px; max-width: 380px; margin: 16px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,.08); }
h1 { font-size: 21px; margin: 0 0 10px; } p { font-size: 16px; font-weight: 600; line-height: 1.7; margin: 0 0 18px; }
a { display: block; background: #262c4a; color: #fff; text-decoration: none; border-radius: 12px; padding: 13px; font-size: 17px; font-weight: 800; }
</style>
</head>
<body>
<div class="b">
    <h1>⏳ الصفحة قعدت مفتوحة وايد</h1>
    <p>ما صار شي ومعلوماتك محفوظة 👍<br>اضغط الزر تحت، تتحدث الصفحة وعاود اللي كنت تديره.</p>
    <a id="go" href="{{ url()->previous() ?: url('/') }}">🔄 حدّث الصفحة</a>
</div>
<script>setTimeout(function () { location.href = document.getElementById('go').href; }, 4000);</script>
</body>
</html>
