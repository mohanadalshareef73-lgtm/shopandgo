@include('portal.partials.head', ['title' => '🛵 طلب توصيل — العربون'])
<div class="card">
    <div style="font-size:15px;line-height:1.9">
        🛒 سلة {{ $cart->retailer_label }} <span class="num">#{{ $cart->id }}</span><br>
        💰 قيمة السلة: <b class="num">{{ number_format($total) }}</b> د.ل<br>
        💵 العربون ({{ (int) $pct }}%): <b class="num" style="font-size:20px;color:#1d8a5f">{{ number_format($deposit) }}</b> د.ل
    </div>
</div>
@if ($done)
    <div class="flash s" style="font-size:16px;line-height:1.8">✅ تم تسجيل طلبك في قائمة التوصيل<br>سنتواصل معك قريباً 💙</div>
@else
<form class="card" method="post" action="{{ route('portal.cart.courier.send', ['id' => $cart->id, 't' => $t]) }}" enctype="multipart/form-data" id="crF">@csrf
    <div style="font-weight:800;font-size:16px;margin-bottom:10px">عبّي بياناتك من فضلك 👇</div>
    <label class="note">الاسم</label><input name="name" class="inp" value="{{ old('name', optional($c)->name) }}" required style="margin:4px 0 10px">
    <label class="note">رقم الهاتف</label><input name="phone" class="inp num" value="{{ old('phone', optional($c)->phone_1 ?: $phone) }}" required inputmode="tel" style="margin:4px 0 10px;text-align:right">
    <label class="note">المدينة</label><input name="city" class="inp" list="lyCities" value="{{ old('city', optional($c)->city ?: 'بنغازي') }}" required style="margin:4px 0 10px">@include('sg.partials.cities')
    <label class="note">المنطقة</label><input name="area" class="inp" value="{{ old('area', optional($c)->area) }}" required placeholder="مثلاً الكيش، الهواري…" style="margin:4px 0 10px">
    <label class="note">العنوان بالتفصيل (اختياري)</label><input name="address" class="inp" value="{{ old('address', optional($c)->address) }}" placeholder="الشارع، أقرب معلم…" style="margin:4px 0 10px">
    <label class="note">لوكيشن (اختياري)</label><input name="location_url" class="inp" value="{{ old('location_url') }}" placeholder="رابط قوقل ماب" style="margin:4px 0 10px;direction:ltr">
    <label class="note">ملاحظة (اختياري)</label><input name="note" class="inp" value="{{ old('note') }}" placeholder="مثلاً: بعد الساعة 5" style="margin:4px 0 12px">
    <div style="font-weight:800;margin-bottom:6px">💳 طريقة دفع العربون</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <label class="inp" style="display:flex;gap:8px;align-items:center;cursor:pointer"><input type="radio" name="pay_type" value="cash" @checked(old('pay_type', 'cash') === 'cash') onchange="crPay()"> 💵 كاش عند الاستلام</label>
        <label class="inp" style="display:flex;gap:8px;align-items:center;cursor:pointer"><input type="radio" name="pay_type" value="bank" @checked(old('pay_type') === 'bank') onchange="crPay()"> 🏦 تحويل مصرفي</label>
    </div>
    <div id="crRc" style="margin-top:10px;display:none"><label class="note">صورة إيصال التحويل</label><input type="file" name="receipt" accept="image/*,application/pdf" class="inp" style="margin-top:4px"></div>
    <button class="btn green block" style="font-size:17px;padding:14px;margin-top:14px">💾 تسجيل طلب التوصيل</button>
    <script>function crPay(){const b=document.querySelector('#crF input[name=pay_type]:checked')?.value==='bank';const r=document.getElementById('crRc');r.style.display=b?'':'none';r.querySelector('input').required=b}crPay();</script>
</form>
@endif
</div></body></html>
