@php $s = \App\Services\AppSettings::all(); $first = preg_split('/\s+/u', trim((string) optional($o->customer)->name))[0] ?? ''; @endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#262c4a">
<title>قيّمنا · {{ $s['shop_name'] ?? 'Shop & Go' }}</title>
<link rel="icon" href="{{ asset('assets/sg/logo.png') }}">
<link rel="stylesheet" href="{{ asset('assets/sg/app.css') }}?v=6">
<style>
.top2 { background: #262c4a; border-radius: 0 0 30px 30px; color: #fff; padding: 40px 24px 70px; text-align: center; }
.top2 .lg { width: 130px; background: #fff; border-radius: 14px; padding: 8px 12px; }
.top2 h1 { font-size: 22px; margin: 18px 0 6px; } .top2 p { color: #c9cde0; font-size: 14px; margin: 0; line-height: 1.8; }
.card2 { background: #fff; margin: -44px 16px 24px; border-radius: 22px; padding: 22px 18px; box-shadow: 0 10px 30px rgba(22,26,46,.08); }
.stars { display: flex; flex-direction: row-reverse; justify-content: center; gap: 6px; margin: 6px 0 18px; }
.stars input { display: none; }
.stars label { font-size: 42px; color: #d9dce4; cursor: pointer; line-height: 1; }
.stars input:checked ~ label, .stars label:hover, .stars label:hover ~ label { color: #f5a623; }
.lbl { display: block; font-weight: 700; font-size: 14px; margin: 0 2px 8px; }
textarea { width: 100%; box-sizing: border-box; border: 1.5px solid var(--line); border-radius: 14px; padding: 12px 14px; font: inherit; font-size: 15px; min-height: 110px; resize: vertical; }
.ph { display: flex; align-items: center; justify-content: center; gap: 8px; border: 1.5px dashed var(--line); border-radius: 14px; padding: 16px; margin-top: 12px; cursor: pointer; color: var(--ink-2); font-weight: 700; }
.ph input { display: none; }
.go { height: 54px; border-radius: 14px; background: var(--brand); color: #fff; font-weight: 700; font-size: 16px; border: 0; width: 100%; margin-top: 16px; font-family: inherit; cursor: pointer; }
.ok2 { text-align: center; padding: 10px 4px; } .ok2 span { font-size: 54px; display: block; }
</style>
</head>
<body>
<div class="app" style="padding-bottom:0">
    <div class="top2"><img class="lg" src="{{ asset('assets/sg/logo.png') }}" alt="Shop & Go"><h1>أهلاً {{ $first }} 💙</h1><p>طلبك <b dir="ltr">{{ $o->code }}</b> وصلك؟ رأيك يهمنا ويساعدنا نتحسنو</p></div>
    <div class="card2">
        @if (session('success') || $done)
            <div class="ok2"><span>🙏</span><h2 style="margin:6px 0">شكراً على تقييمك</h2><p style="color:var(--ink-3);line-height:1.8">وصلنا رأيك، وبعد المراجعة ممكن يطلع في صفحة تقييمات Shop & Go.</p>
                <a href="{{ route('reviews.public') }}" style="color:var(--brand);font-weight:700">شوف تقييمات الزبائن ←</a></div>
        @else
            @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
            <form method="post" action="{{ route('review.store', ['order' => $o->id, 't' => $t]) }}" enctype="multipart/form-data" onsubmit="this.querySelector('.go').disabled=true;this.querySelector('.go').textContent='⏳ يتبعت…'">
                @csrf
                <span class="lbl" style="text-align:center">كم نجمة تعطينا؟</span>
                <div class="stars">@for ($i = 5; $i >= 1; $i--)<input type="radio" name="rating" id="st{{ $i }}" value="{{ $i }}" @checked((int) old('rating', 5) === $i)><label for="st{{ $i }}" title="{{ $i }}">★</label>@endfor</div>
                <label class="lbl" for="body">شن رأيك؟ <span style="font-weight:400;color:var(--ink-3)">(اختياري)</span></label>
                <textarea name="body" id="body" maxlength="1000" placeholder="مثلاً: الطلب وصل بسرعة والتعامل ممتاز…">{{ old('body') }}</textarea>
                <label class="ph"><input type="file" name="photo" accept="image/*" onchange="this.parentNode.querySelector('b').textContent=this.files[0]?'✓ '+this.files[0].name:'📷 زيد صورة لطلبك (اختياري)'"><b>📷 زيد صورة لطلبك (اختياري)</b></label>
                <button class="go">إرسال التقييم</button>
            </form>
        @endif
    </div>
</div>
</body>
</html>
