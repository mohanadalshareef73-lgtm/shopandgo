@extends('customers._auth')
@section('t', 'رمز التحقق')
@section('body')
<div class="top2 sm"><div class="c"></div><img class="lg" src="{{ asset('assets/sg/logo.png') }}" alt=""><h1>رمز التحقق</h1><p>أرسلنا رمزاً من 6 أرقام إلى واتساب الرقم <b class="num" style="color:#fff;direction:ltr;display:inline-block">{{ substr($phone, 0, 3) }}•••{{ substr($phone, -3) }}</b></p></div>
<form class="card2" method="post" action="{{ route('customer.verify.submit') }}" id="vf">
    @csrf
    @if ($purpose === 'signup')<div class="steps"><i class="on"></i><i class="on"></i><i></i></div>@endif
    @if (session('error'))<div class="flash err">{{ session('error') }}</div>@endif
    @if (session('success'))<div class="flash ok">{{ session('success') }}</div>@endif
    @if (session('warning'))<div class="flash warn">{{ session('warning') }}</div>@endif
    <input type="hidden" name="code" id="code">
    <div class="otp" id="otp">@for ($i = 0; $i < 6; $i++)<input inputmode="numeric" maxlength="1" autocomplete="{{ $i === 0 ? 'one-time-code' : 'off' }}" aria-label="رقم {{ $i + 1 }}">@endfor</div>
    <button class="go">تأكيد</button>
    <div class="hint2">🔐 لا تشارك هذا الرمز مع أي شخص. الرمز صالح لمدة 10 دقائق.</div>
</form>
<form method="post" action="{{ route('customer.verify.resend') }}" class="links">@csrf لم يصلك الرمز؟ <button id="rs" style="border:0;background:none;color:var(--wa);font:inherit;font-weight:700;cursor:pointer" {{ $wait ? 'disabled' : '' }} data-wait="{{ $wait }}">إعادة الإرسال</button></form>
@endsection
@push('js')
<script>
(function () {
  const ins = [...document.querySelectorAll('#otp input')], code = document.getElementById('code');
  const sync = () => code.value = ins.map(i => i.value).join('');
  ins.forEach((i, n) => {
    i.addEventListener('input', () => { i.value = i.value.replace(/[^0-9٠-٩]/g, '').slice(-1); sync(); if (i.value && ins[n + 1]) ins[n + 1].focus(); if (code.value.length === 6) document.getElementById('vf').requestSubmit(); });
    i.addEventListener('keydown', e => { if (e.key === 'Backspace' && !i.value && ins[n - 1]) ins[n - 1].focus(); });
    i.addEventListener('paste', e => { const d = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6); if (d) { e.preventDefault(); d.split('').forEach((c, k) => ins[k] && (ins[k].value = c)); sync(); if (d.length === 6) document.getElementById('vf').requestSubmit(); } });
  });
  ins[0].focus();
  const rs = document.getElementById('rs'); let w = +rs.dataset.wait;
  if (w > 0) { const t = setInterval(() => { w--; rs.textContent = w > 0 ? 'إعادة الإرسال بعد ' + w + ' ث' : 'إعادة الإرسال'; if (w <= 0) { rs.disabled = false; clearInterval(t); } }, 1000); }
})();
</script>
@endpush
