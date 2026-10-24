@include('portal.partials.head', ['title' => '🛵 طلب توصيل'])
@php $f = fn ($n) => \App\Services\TreasuryService::fmt($n); @endphp
<div class="card">
    <div style="font-size:15px">أهلاً <b>{{ optional($c)->name }}</b> 👋</div>
    <div class="note">كودك: <b class="num">{{ optional($c)->code }}</b></div>
</div>
@if ($o->delivered)
    <div class="card" style="text-align:center"><div style="font-size:40px">✅</div><b>الطلبية {{ $o->code }} تسلمت</b><div class="note">شكراً لاختياركم Shop & Go 💙</div></div>
@elseif ($dl)
    <div class="card" style="text-align:center">
        <div style="font-size:40px">{{ $dl->status === 'delivered' ? '✅' : '🛵' }}</div>
        <b style="font-size:17px">{{ $dl->status === 'delivered' ? 'طلبك تسلّم' : 'تم تسجيل طلبك في قائمة التوصيل' }}</b>
        @if ($dl->status !== 'delivered')<div style="margin:10px 0;font-size:15px">سنتواصل معك قريباً 💙</div>@endif
        <div class="note">{{ $dl->recipient_name }} · <span class="num">{{ $dl->recipient_phone }}</span><br>{{ trim(($dl->city ? $dl->city . ' - ' : '') . $dl->address) }}</div>
        <div class="note" style="margin-top:8px">الباقي: <b class="num">{{ $f($dl->amount) }}</b> د.ل · {{ ['cash' => '💵 كاش عند الاستلام', 'bank' => '🏦 تحويل مصرفي', 'mixed' => 'كاش + مصرف', 'paid' => '✅ مدفوعة'][$dl->pay_type] ?? '' }}</div>
    </div>
@else
    <form method="post" action="{{ route('deliv.req.store', ['order' => $o->id, 't' => $t]) }}" enctype="multipart/form-data" id="drF">@csrf
    <div class="card">
        <b>📦 الطلبيات اللي توصلك</b>
        @foreach ($orders as $x)
            <label style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px dashed #e5e7ee">
                <input type="checkbox" name="ids[]" value="{{ $x->id }}" data-rem="{{ max(0, $x->remaining()) }}" checked style="width:20px;height:20px" onchange="drSum()">
                <span style="flex:1"><b class="num">{{ $x->code }}</b></span>
                <span class="note">الباقي <b class="num">{{ $f(max(0, $x->remaining())) }}</b> د.ل</span>
            </label>
        @endforeach
        <div style="display:flex;justify-content:space-between;margin-top:10px;font-size:16px"><b>💰 الباقي الكلي</b><b><span class="num" id="drTot">{{ $f($orders->sum(fn ($x) => max(0, $x->remaining()))) }}</span> د.ل</b></div>
    </div>
    <div class="card">
        <b>📍 بيانات التوصيل</b>
        <div style="display:grid;gap:10px;margin-top:10px">
            <input name="name" class="inp" placeholder="الاسم" value="{{ old('name', optional($c)->name) }}" required>
            <input name="phone" class="inp num" placeholder="رقم الهاتف" inputmode="tel" value="{{ old('phone', optional($c)->phone_1) }}" required style="text-align:right">
            <input name="city" class="inp" placeholder="المدينة" list="lyCities" value="{{ old('city', optional($c)->city ?: 'بنغازي') }}" required>@include('sg.partials.cities')
            <input name="area" class="inp" placeholder="المنطقة (مثلاً الكيش، الهواري…)" value="{{ old('area', optional($c)->area) }}" required>
            <input name="address" class="inp" placeholder="العنوان بالتفصيل (اختياري: الشارع، أقرب معلم…)" value="{{ old('address', optional($c)->address) }}">
            <input name="location_url" class="inp" placeholder="رابط موقعك من خرائط Google (اختياري)" value="{{ old('location_url', optional($c)->location_url) }}" style="direction:ltr">
            <input name="note" class="inp" placeholder="ملاحظة (اختياري)" value="{{ old('note') }}">
        </div>
    </div>
    <div class="card">
        <b>💳 طريقة الدفع</b>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
            <label class="inp" style="display:flex;gap:8px;align-items:center;cursor:pointer"><input type="radio" name="pay_type" value="cash" @checked(old('pay_type', 'cash') === 'cash') onchange="drPay()"> 💵 كاش عند الاستلام</label>
            <label class="inp" style="display:flex;gap:8px;align-items:center;cursor:pointer"><input type="radio" name="pay_type" value="bank" @checked(old('pay_type') === 'bank') onchange="drPay()"> 🏦 تحويل مصرفي</label>
        </div>
        <div id="drRc" style="margin-top:10px;display:none">
            <label class="note">صورة إيصال التحويل</label>
            <input type="file" name="receipt" accept="image/*,application/pdf" class="inp" style="margin-top:4px">
        </div>
    </div>
    <button class="btn green block" style="font-size:17px;padding:15px">💾 تسجيل طلب التوصيل</button>
    </form>
    <script>
    function drSum(){let t=0;document.querySelectorAll('#drF input[name="ids[]"]:checked').forEach(x=>t+=parseFloat(x.dataset.rem||0));document.getElementById('drTot').textContent=t.toLocaleString('en-US',{maximumFractionDigits:2})}
    function drPay(){const b=document.querySelector('#drF input[name=pay_type]:checked')?.value==='bank';const r=document.getElementById('drRc');r.style.display=b?'':'none';r.querySelector('input').required=b}
    drPay();
    </script>
@endif
</div></body></html>
