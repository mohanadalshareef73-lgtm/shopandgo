@include('portal.partials.head', ['title' => '🛵 اطلب مندوب'])
@php $f = fn ($n) => \App\Services\TreasuryService::fmt($n); @endphp
<div class="card">
    <div style="font-size:15px">أهلاً <b>{{ optional($c)->name }}</b> 👋</div>
    <div class="note">كودك: <b class="num">{{ optional($c)->code }}</b></div>
</div>
@if ($o->delivered)
    <div class="card" style="text-align:center"><div style="font-size:40px">✅</div><b>الطلبية {{ $o->code }} تسلمت</b><div class="note">شكراً لاختياركم Shop & Go 💙</div></div>
@elseif ($dl)
    @php $stl = ['requested' => ['قيد استلام المندوب', '#8a5a00'], 'assigned' => ['قيد استلام المندوب', '#8a5a00'], 'out' => ['قيد التوصيل 🛵', '#1d4f8f'], 'problem' => ['قيد التوصيل', '#1d4f8f'], 'delivered' => ['مستلمة ✅', '#0f6b4c']][$dl->status] ?? [$dl->statusLabel(), '#333']; @endphp
    <div class="card" style="text-align:center">
        <div style="font-size:40px">🛵</div>
        <b style="font-size:17px">طلب التوصيل مسجل</b>
        <div style="margin:10px 0"><span style="background:#f2f3f7;color:{{ $stl[1] }};border-radius:99px;padding:6px 14px;font-weight:800">{{ $stl[0] }}</span></div>
        <div class="note">{{ $dl->recipient_name }} · <span class="num">{{ $dl->recipient_phone }}</span><br>{{ $dl->address }}</div>
        @if ($dl->courier)<div class="note" style="margin-top:6px">المندوب: <b>{{ $dl->courier->name }}</b></div>@endif
        <div class="note" style="margin-top:8px">المبلغ اللي تدفعه للمندوب: <b class="num">{{ $f($dl->toCollect()) }}</b> د.ل</div>
    </div>
@else
    <form method="post" action="{{ route('deliv.req.store', ['order' => $o->id, 't' => $t]) }}">@csrf
    <div class="card">
        <b>📦 الطلبيات اللي توصلك</b>
        @foreach ($orders as $x)
            <label style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px dashed #e5e7ee">
                <input type="checkbox" name="ids[]" value="{{ $x->id }}" checked style="width:20px;height:20px">
                <span style="flex:1"><b class="num">{{ $x->code }}</b></span>
                <span class="note">الباقي <b class="num">{{ $f(max(0, $x->remaining())) }}</b> د.ل</span>
            </label>
        @endforeach
        @if ($fee > 0)<div class="note" style="margin-top:8px">🛵 أجرة التوصيل: <b class="num">{{ $f($fee) }}</b> د.ل (تدفعها للمندوب)</div>@endif
    </div>
    <div class="card">
        <b>📍 بيانات التوصيل</b>
        <div style="display:grid;gap:10px;margin-top:10px">
            <input name="name" class="inp" placeholder="الاسم" value="{{ old('name', optional($c)->name) }}" required>
            <input name="phone" class="inp num" placeholder="رقم الهاتف" inputmode="tel" value="{{ old('phone', optional($c)->phone_1) }}" required style="text-align:right">
            <input name="city" class="inp" placeholder="المدينة" list="cities" value="{{ old('city', 'بنغازي') }}" required>
            <datalist id="cities"><option value="بنغازي"><option value="طرابلس"><option value="مصراتة"><option value="البيضاء"><option value="طبرق"><option value="درنة"><option value="المرج"><option value="أجدابيا"><option value="سرت"><option value="سبها"></datalist>
            <div class="note" style="margin-top:-4px">لو برا بنغازي نتواصلوا معاك على طريقة التوصيل</div>
            <input name="address" class="inp" placeholder="العنوان بالتفصيل (الشارع، أقرب معلم…)" value="{{ old('address', optional($c)->address) }}" required>
            <input name="location_url" class="inp" placeholder="رابط موقعك من خرائط Google (اختياري)" value="{{ old('location_url', optional($c)->location_url) }}" style="direction:ltr">
            <input name="note" class="inp" placeholder="ملاحظة للمندوب (اختياري)" value="{{ old('note') }}">
        </div>
    </div>
    <button class="btn green block" style="font-size:17px;padding:15px">💾 حفظ — اطلب مندوب</button>
    </form>
@endif
</div></body></html>
