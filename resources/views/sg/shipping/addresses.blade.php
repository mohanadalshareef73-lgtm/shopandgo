@extends('sg.layout')
@section('title', 'عناوين الشحن')
@section('content')
<div class="crumb"><a href="{{ route('sg.shipping') }}">الشحن</a> › عناوين الشحن</div>
<div class="top">
    <div><h1>إرسال عناوين الشحن</h1><div class="sub">كود شحن للعميل + كل عنوان يوصله في رسالة واتساب بروحها</div></div>
    <div class="acts"><button class="sg-burger">☰</button></div>
</div>
<form method="post" action="{{ route('sg.shipping.addresses.send') }}" class="side-lay" style="grid-template-columns:1fr 1fr">
    @csrf
    <div class="card">
        <h3>1. العميل</h3>
        <input class="inp mb" id="cq" placeholder="ابحث: كود، هاتف، اسم… (يفلتر وأنت تكتب)" autocomplete="off">
        <select name="customer_id" id="cs" class="inp" size="7" style="height:auto">
            <option value="" selected>➕ عميل جديد</option>
            @foreach ($customers as $c)<option value="{{ $c->id }}" data-sp="{{ $c->ship_prices }}">{{ $c->code }} · {{ $c->name }} · {{ $c->phone_1 }}</option>@endforeach
        </select>
        <div id="newC" class="mt">
            <div class="f"><label class="l">الاسم</label><input name="name" class="inp" value="{{ old('name') }}"></div>
            <div class="grid g2 f"><div><label class="l">رقم الهاتف</label><input name="phone" class="inp numr" value="{{ old('phone') }}"></div>
                <div><label class="l">الكود <span class="note">(اختياري — فاضي ياخد آخر أرقام الهاتف)</span></label><input name="code" class="inp numr" value="{{ old('code') }}"></div></div>
        </div>
    </div>
    <div class="card">
        <h3>2. العناوين والأسعار</h3>
        @foreach ($origins as $k => $og)
            <div class="f" style="border:1px solid var(--line);border-radius:12px;padding:10px 12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <label class="ck" style="flex:1;min-width:140px"><input type="checkbox" name="origins[]" value="{{ $k }}" checked> {{ $og['flag'] }} <b>{{ $og['name'] }}</b> <span class="note">{{ $og['city'] }}</span></label>
                <span class="note">سعر الكيلو/CBM $</span><input name="price[{{ $k }}]" class="inp numr pr" data-k="{{ $k }}" data-base="{{ $og['per_kg'] }}" value="{{ $og['per_kg'] }}" style="width:90px;height:38px">
                <small class="note dsc" style="width:100%"></small>
            </div>
        @endforeach
        <label class="ck f"><input type="checkbox" name="with_image" value="1" @checked($hasCard) @disabled(!$hasCard)> أرسل صورة الكود مع أول رسالة @if (!$hasCard)<a class="note" href="{{ route('sg.shipping.prices') }}#card">(ارفع الصورة الثابتة أول)</a>@endif</label>
        <div class="note mb">لو نزّلت السعر تحت سعر البلد: يتسجل سعر خاص للعميل (يُستعمل في حساب شحناته). الرسالة فيها: الاسم + العنوان + الكود فقط، وكل عنوان (جوي/بحري) رسالة بروحه.</div>
        <button class="btn green block" data-confirm="نرسلو العناوين؟">💬 أرسل العناوين</button>
    </div>
</form>
<script>
(function () {
  const q = document.getElementById('cq'), s = document.getElementById('cs'), nc = document.getElementById('newC');
  q.addEventListener('input', () => { const t = q.value.trim(); let first = null; [...s.options].forEach(o => { const ok = !o.value || !t || o.text.includes(t); o.hidden = !ok; if (ok && o.value && !first) first = o; }); if (t && first) { s.value = first.value; upd(); } });
  function upd() {
    nc.hidden = !!s.value;
    let sp = {}; try { sp = JSON.parse(s.selectedOptions[0]?.dataset.sp || '{}') || {}; } catch (e) {}
    document.querySelectorAll('.pr').forEach(i => { i.value = sp[i.dataset.k] ?? i.dataset.base; show(i); });
  }
  function show(i) { const b = +i.dataset.base, v = parseFloat(i.value) || 0, el = i.parentElement.querySelector('.dsc'); el.textContent = v < b ? 'كان بـ ' + b + '$ وقعد بـ ' + v + '$ لأنك عميل خاص 🌟' : ''; }
  s.addEventListener('change', upd);
  document.querySelectorAll('.pr').forEach(i => i.addEventListener('input', () => show(i)));
  upd();
})();
</script>
@endsection
