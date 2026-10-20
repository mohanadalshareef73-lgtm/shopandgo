@include('portal.partials.head', ['title' => '🛵 مندوب يستلم العربون'])
<div class="card">
    <div style="font-size:15px;line-height:1.9">
        🛒 سلة {{ $cart->retailer_label }} <span class="num">#{{ $cart->id }}</span><br>
        💰 قيمة السلة: <b class="num">{{ number_format($total) }}</b> د.ل<br>
        💵 العربون ({{ (int) $pct }}%): <b class="num" style="font-size:20px;color:#1d8a5f">{{ number_format($deposit) }}</b> د.ل
        @if ($fee > 0)<br>🛵 أجرة المندوب: <b class="num">{{ number_format($fee) }}</b> د.ل @endif
    </div>
</div>
@if ($done)
    <div class="flash s" style="font-size:16px;line-height:1.8">✅ وصلنا طلبك! المندوب بيكلمك ويجيك يستلم العربون، وأول ما يستلم يجيك الإيصال على الواتساب وطلبك يتحجز 💙</div>
@else
<form class="card" method="post" action="{{ route('portal.cart.courier.send', ['id' => $cart->id, 't' => $t]) }}">@csrf
    <div style="font-weight:800;font-size:16px;margin-bottom:10px">عبّي بياناتك من فضلك 👇</div>
    <label class="note">الاسم</label><input name="name" class="inp" value="{{ old('name', optional($c)->name) }}" required style="margin:4px 0 10px">
    <label class="note">رقم الهاتف</label><input name="phone" class="inp num" value="{{ old('phone', optional($c)->phone_1 ?: $phone) }}" required inputmode="tel" style="margin:4px 0 10px;text-align:right">
    <label class="note">المدينة والمنطقة</label><input name="address" class="inp" value="{{ old('address', optional($c)->address) }}" required placeholder="مثلاً: طرابلس - حي الأندلس" style="margin:4px 0 10px">
    <label class="note">لوكيشن (اختياري)</label><input name="location_url" class="inp" value="{{ old('location_url') }}" placeholder="رابط قوقل ماب" style="margin:4px 0 10px;direction:ltr">
    <label class="note">ملاحظة للمندوب (اختياري)</label><input name="note" class="inp" value="{{ old('note') }}" placeholder="مثلاً: بعد الساعة 5" style="margin:4px 0 14px">
    <button class="btn green block" style="font-size:17px;padding:14px">🛵 ابعتلي المندوب</button>
</form>
@endif
</div></body></html>
