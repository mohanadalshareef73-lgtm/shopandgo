@extends('customers._auth')
@section('t', 'إنشاء حساب')
@section('body')
<div class="top2 sm"><div class="c"></div><img class="lg" src="{{ asset('assets/sg/logo.png') }}" alt=""><h1>إنشاء حساب جديد</h1><p>سجّل بياناتك، وبعد موافقة الإدارة يصلك كود العميل عبر واتساب.</p></div>
<form class="card2" method="post" action="{{ route('customer.register.store') }}" id="rf" autocomplete="on">
    @csrf
    <div class="steps"><i class="on"></i><i class="{{ ($s['otp_enabled'] ?? true) ? '' : 'on' }}"></i><i></i></div>
    @if (session('error'))<div class="flash err">{{ session('error') }}</div>@endif
    @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif
    <input type="hidden" name="ts" value="{{ $ts }}">
    <div class="hp" aria-hidden="true"><label>Website</label><input name="website" tabindex="-1" autocomplete="off"></div>
    <div class="f2"><label>الاسم الكامل</label><div class="box"><span>👤</span><input name="name" value="{{ old('name') }}" required minlength="3" maxlength="80" autocomplete="name" style="direction:rtl;text-align:right;font-family:Somar"></div></div>
    <div class="f2"><label>رقم الهاتف (عليه واتساب)</label><div class="box"><span>📱</span><input name="phone_1" value="{{ old('phone_1') }}" required inputmode="tel" maxlength="16" placeholder="09xxxxxxxx" autocomplete="tel"></div>@if ($s['otp_enabled'] ?? true)<small>سيصلك رمز تحقق على واتساب هذا الرقم</small>@endif</div>
    <div class="f2"><label>رقم هاتف آخر <span style="font-weight:400;color:var(--ink-3)">(اختياري)</span></label><div class="box"><span>☎️</span><input name="phone_2" value="{{ old('phone_2') }}" inputmode="tel" maxlength="16"></div></div>
    <div class="f2"><label>المدينة</label><div class="box"><span>🏙</span><input name="city" list="lyCities" value="{{ old('city', 'بنغازي') }}" required maxlength="80" style="direction:rtl;text-align:right;font-family:Somar"></div></div>@include('sg.partials.cities')
    <div class="f2"><label>المنطقة</label><div class="box"><span>📍</span><input name="area" value="{{ old('area') }}" required maxlength="120" placeholder="مثال: الكيش، الهواري، حي الأندلس" style="direction:rtl;text-align:right;font-family:Somar"></div></div>
    <div class="f2"><label>العنوان بالتفصيل <span style="font-weight:400;color:var(--ink-3)">(اختياري)</span></label><div class="box"><span>🏠</span><input name="address" value="{{ old('address') }}" maxlength="150" placeholder="الشارع، أقرب معلم" style="direction:rtl;text-align:right;font-family:Somar"></div></div>
    <div class="f2"><label>كلمة المرور</label><div class="box"><span>🔒</span><input type="password" name="password" id="pw" required minlength="8" autocomplete="new-password"></div><div class="pwm"><b id="pwb"></b></div><small id="pwt">8 خانات على الأقل، ويفضّل أن تحتوي على أرقام وحروف</small></div>
    <div class="f2"><label>تأكيد كلمة المرور</label><div class="box"><span>🔒</span><input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></div></div>
    <label class="agree"><input type="checkbox" name="agree" value="1" required @checked(old('agree'))><span>أقرّ بأن البيانات صحيحة، وأوافق على أن الحساب لا يُفعَّل إلا بعد مراجعة الإدارة.</span></label>
    <button class="go" id="sb">متابعة</button>
    <div class="links" style="margin-top:14px">لديك حساب؟ <a href="{{ route('customer.login') }}" style="color:var(--brand)">تسجيل الدخول</a></div>
</form>
@endsection
@push('js')
<script>
(function () {
  const pw = document.getElementById('pw'), b = document.getElementById('pwb'), t = document.getElementById('pwt');
  pw.addEventListener('input', () => { const v = pw.value; let s = 0; if (v.length >= 8) s++; if (v.length >= 12) s++; if (/\d/.test(v) && /[^\d]/.test(v)) s++; if (/[^\w؀-ۿ]/.test(v)) s++;
    const c = ['#c4352f', '#c4352f', '#d98a00', '#12805c', '#12805c'][s]; b.style.width = (s / 4 * 100) + '%'; b.style.background = c;
    t.textContent = v.length < 8 ? '8 خانات على الأقل' : (['ضعيفة', 'ضعيفة', 'متوسطة', 'قوية', 'قوية جداً'][s]); });
  document.getElementById('rf').addEventListener('submit', () => { const s = document.getElementById('sb'); setTimeout(() => { s.disabled = true; s.textContent = 'جارٍ الإرسال…'; }, 0); });
})();
</script>
@endpush
