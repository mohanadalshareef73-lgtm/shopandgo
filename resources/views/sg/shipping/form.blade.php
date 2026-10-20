@extends('sg.layout')
@section('title', $o ? 'تعديل الشحنة ' . $o->code : 'شحنة جديدة')
@section('bodyClass', 'soft')
@php
    use App\Services\ShippingService as SS;
    $sel = old('customer_id', optional($o)->customer_id);
    $og0 = old('origin', optional($o)->ship_origin ?: array_key_first($origins));
    $stage0 = old('stage', $o ? ($o->arrived ? 'arrived' : ($o->ship_stage ?: 'warehouse')) : 'warehouse');
@endphp
@push('style')
<style>
.ogc { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 16px; }
.ogc label { border: 1.5px solid var(--line); border-radius: 16px; padding: 14px; display: block; cursor: pointer; background: #fff; }
.ogc input { position: absolute; opacity: 0; pointer-events: none; }
.ogc label:has(input:checked) { border: 2px solid var(--brand); background: #f6f7fb; }
.ogc .fl { font-size: 26px; } .ogc b { display: block; font-size: 15px; margin-top: 4px; } .ogc span.s { font-size: 12.5px; color: var(--ink-3); } .ogc .pr { font-family: InterNum; font-weight: 800; font-size: 18px; margin-top: 6px; display: block; }
.fgrid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 16px; }
.fgrid .l, .lb { font-size: 13.5px; font-weight: 700; margin-bottom: 7px; display: block; }
.fgrid .inp { height: 46px; }
.calc { background: var(--brand); color: #fff; border-radius: 18px; padding: 18px; }
.calc .r { display: flex; justify-content: space-between; padding: 7px 0; font-size: 14px; color: #c9cde0; border-bottom: 1px solid rgba(255,255,255,.1); }
.calc .r b { color: #fff; font-family: InterNum; direction: ltr; unicode-bidi: isolate; }
.calc .big { font-family: InterNum; font-size: 34px; font-weight: 800; margin-top: 10px; } .calc .big small { font-family: Somar; font-size: 14px; color: #c9cde0; font-weight: 400; }
.spc { font-size: 12px; font-weight: 700; color: #8a5a00; background: #fff3d6; border-radius: 99px; padding: 3px 10px; display: none; }
@media (max-width: 900px) { .fgrid, .ogc { grid-template-columns: 1fr 1fr; } }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.shipping') }}">الشحن</a> › {{ $o ? 'تعديل ' . $o->code : 'شحنة جديدة' }}</div>
<div class="top"><div><h1>{{ $o ? 'تعديل الشحنة' : 'شحنة جديدة' }}</h1><div class="sub">العميل يشتري بنفسه، ونحن نشحن له</div></div><div class="acts"><a class="btn ghost" href="{{ route('sg.shipping') }}">← رجوع</a><button class="sg-burger">☰</button></div></div>

<form method="post" action="{{ $o ? route('sg.shipping.update', $o) : route('sg.shipping.store') }}" id="sf">
@csrf @if ($o) @method('PUT') @endif
<div class="side-lay">
<div class="card" style="padding:22px 24px">
    <div class="fgrid" style="grid-template-columns:2fr 1fr;margin-bottom:16px">
        <div><span class="l">العميل <span class="spc" id="spc"></span></span>
            <input class="inp mb" id="custQ" placeholder="ابحث: اكتب أرقام الكود أو الهاتف أو الاسم…" autocomplete="off" oninput="const t=this.value.trim();const s=document.getElementById('cust');let first=null;[...s.options].forEach(o=>{const ok=!o.value||!t||o.text.includes(t);o.hidden=!ok;if(ok&&o.value&&!first)first=o;});if(t&&first){s.value=first.value;s.dispatchEvent(new Event('change'));}window.sgNoCust(t,!first);">
            <select name="customer_id" id="cust" class="inp"><option value="">— اختر العميل —</option>@foreach ($customers as $c)<option value="{{ $c->id }}" data-sp="{{ $c->ship_prices }}" @selected($sel == $c->id)>{{ $c->name }} · {{ $c->code }} · {{ $c->phone_1 }}</option>@endforeach</select>
            <a href="#" class="note" style="display:inline-block;margin-top:6px;font-weight:800" onclick="const b=document.getElementById('newC');b.style.display=b.style.display==='none'?'flex':'none';if(b.style.display==='flex'){document.getElementById('cust').value='';b.querySelector('input').focus()}return false">➕ عميل جديد (مش مسجل)</a>
            <div id="noCust" class="hint" style="display:none;margin-top:6px;background:#fff4e5;color:#8a5a00">ما لقيناش العميل في المنظومة — سجّله جديد تحت 👇 وتوصله رسالة الشحنة وبيانات دخول حسابه</div>
            <div id="newC" style="display:{{ old('new_phone') ? 'flex' : 'none' }};gap:8px;margin-top:6px;flex-wrap:wrap">
                <input name="new_name" class="inp" style="flex:1 1 180px" placeholder="اسم العميل *" value="{{ old('new_name') }}">
                <input name="new_phone" class="inp numr" style="flex:1 1 150px" placeholder="رقم هاتفه 09… *" inputmode="tel" value="{{ old('new_phone') }}">
                <input name="new_phone2" class="inp numr" style="flex:1 1 150px" placeholder="رقم ثاني (اختياري)" inputmode="tel" value="{{ old('new_phone2') }}">
                <input name="new_address" class="inp" style="flex:2 1 260px" placeholder="العنوان / المدينة (اختياري)" value="{{ old('new_address') }}">
            </div>
            <script>
            // ما لقيناش العميل: نفتحو «عميل جديد» ونعبّو اللي كتبته (رقم ← الهاتف، كلام ← الاسم)
            window.sgNoCust = function (t, none) {
              const b = document.getElementById('newC'), h = document.getElementById('noCust');
              if (!none || t.length < 3) { h.style.display = 'none'; return; }
              h.style.display = 'block'; b.style.display = 'flex'; document.getElementById('cust').value = '';
              const d = t.replace(/\D/g, '');
              if (d.length >= 7 && d.length === t.replace(/\s/g, '').length) b.querySelector('[name=new_phone]').value = t; else if (!/\d/.test(t)) b.querySelector('[name=new_name]').value = t;
            };
            </script>
            <div class="note" style="font-size:12px">لو الرقم عنده حساب يتربط بيه، ولو لا ينفتحله حساب جديد أوتوماتيك</div></div>
        <div><span class="l">رقم الشحنة</span><div class="inp num" style="display:flex;align-items:center">{{ $next }}</div></div>
    </div>
    <span class="lb">الشحن من</span>
    <div class="ogc">
        @foreach ($origins as $k => $og)
            <label><input type="radio" name="origin" value="{{ $k }}" data-kg="{{ $og['per_kg'] }}" data-min="{{ $og['min'] ?? 0 }}" data-tiers='@json(\App\Services\ShippingService::tiers($og))' data-modes='@json(collect(\App\Services\ShippingService::addrs($og))->mapWithKeys(fn ($a) => [$a['mode'] => (float) $a['per_kg']]))' @checked($og0 === $k)><span class="fl">{{ $og['flag'] }}</span><b>{{ $og['name'] }}</b><span class="s">{{ $og['city'] ? 'مخزن ' . $og['city'] : '' }}</span><span class="pr" style="font-size:15px">@foreach (\App\Services\ShippingService::addrs($og) as $a){{ \App\Services\ShippingService::MODES[$a['mode']] ?? '' }} {{ $a['per_kg'] }}$ @endforeach<small style="font-size:11px;color:var(--ink-3)">/ كغ</small></span></label>
        @endforeach
    </div>
    <div class="fgrid">
        <div><span class="l">رقم التتبع</span><input name="tracking_no" class="inp num" value="{{ old('tracking_no', optional($o)->tracking_no) }}" style="direction:ltr;text-align:right"></div>
        <div><span class="l">عدد الطرود</span><input name="bags" class="inp numr" value="{{ old('bags', optional($o)->bags_num ?: 1) }}"></div>
        <div><span class="l">نوع الشحن</span><select name="package_type" id="ptype" class="inp">@foreach ($types as $t)<option @selected(old('package_type', optional($o)->package_type ?: ($types[0] ?? '')) == $t)>{{ $t }}</option>@endforeach</select></div>
        <div><span class="l">المنصة (شرى منها)</span><input name="ship_platform" class="inp" list="plats" value="{{ old('ship_platform', optional($o)->ship_platform) }}" placeholder="SHEIN · Amazon · Temu…"><datalist id="plats">@foreach ($platforms as $pl)<option value="{{ $pl }}">@endforeach</datalist></div>
        <div><span class="l" data-u="w">الوزن (كغ)</span><input name="kilo" id="kg" class="inp numr" value="{{ old('kilo', optional($o)->kilo) }}" placeholder="يُكتب عند الوصول للمخزن"></div>
        <div><span class="l" data-u="p">سعر الكيلو ($)</span><input name="per_kg" id="pk" class="inp numr" value="{{ old('per_kg', optional($o)->usd_per_kg) }}"></div>
        <div><span class="l" data-u="d">تخفيض على الكيلو ($)</span><input name="discount" id="disc" class="inp numr" value="{{ old('discount', optional($o)->usd_discount ?: 0) }}"></div>
        <div><span class="l">الحالة</span><select name="stage" id="stage" class="inp">@foreach (['expected', 'warehouse', 'transit', 'arrived'] as $k)<option value="{{ $k }}" @selected($stage0 === $k)>{{ SS::STAGES[$k][0] }}</option>@endforeach</select></div>
        <div><span class="l">مكان التخزين (عند الوصول)</span><input name="storage" class="inp" value="{{ old('storage', optional($o)->storage_num) }}" list="shelves" placeholder="A-4"></div>
        @php $cur0 = old('ship_cur', optional($o)->ship_cur ?: 'usd'); $amt0 = old('ship_amt', $o ? ($cur0 === 'lyd' ? $o->ship_price : $o->usd_amount) : ''); @endphp
        <div><span class="l">سعر الشحن النهائي <small class="note">(يتحسب من الوزن، وتقدر تعدله)</small></span>
            <div style="display:flex;gap:6px"><input name="ship_amt" id="amt" class="inp numr" value="{{ $amt0 }}" placeholder="يتحسب أوتوماتيك" style="flex:1">
            <select name="ship_cur" id="cur" class="inp" style="width:90px"><option value="usd" @selected($cur0 === 'usd')>$ دولار</option><option value="lyd" @selected($cur0 === 'lyd')>د.ل دينار</option></select></div></div>
        @php $pay0 = old('ship_pay', optional($o)->ship_pay ?: 'cash'); @endphp
        <div id="payWrap"><span class="l">سعر الشحن المعتمد <small class="note">(يتغير وقت التسليم)</small></span>
            <select name="ship_pay" id="shipPay" class="inp"><option value="cash" @selected($pay0 === 'cash')>💵 كاش</option><option value="bank" @selected($pay0 === 'bank')>🏦 مصرف</option></select></div>
        <div><span class="l">ملاحظة</span><input name="note" class="inp" value="{{ old('note', optional($o)->note) }}" placeholder="مثال: يحتوي على زجاج"></div>
    </div>
    <datalist id="shelves">@foreach ($shelves as $sh)<option value="{{ $sh }}">@endforeach</datalist>
    @if (!$o)<label class="ck" style="margin-top:16px"><input type="checkbox" name="send_whatsapp" value="1" checked> إرسال رسالة واتساب للعميل بتسجيل الشحنة والوزن والسعر</label>@endif
</div>
<div>
    <div class="calc">
        <div style="font-size:14px;color:#c9cde0;margin-bottom:6px">حساب الشحن</div>
        <div class="r"><span>الوزن</span><b id="cKg">—</b></div>
        <div class="r"><span>سعر الكيلو</span><b id="cPk">—</b></div>
        <div class="r"><span>التخفيض</span><b id="cDs" style="color:#7fe0b2">—</b></div>
        <div class="r"><span>الكيلو بعد التخفيض</span><b id="cNet">—</b></div>
        <div class="big"><span id="cUsd">0</span> <small>$</small></div>
        <div class="r" style="margin-top:8px"><span>💵 كاش <small id="rCash" style="opacity:.7"></small></span><b id="cCash">—</b></div>
        <div class="r"><span>🏦 مصرف <small id="rBank" style="opacity:.7"></small></span><b id="cBank">—</b></div>
        <div style="color:#c9cde0;font-size:13px" id="cLyd">—</div>
    </div>
    <button class="btn block lg" style="margin-top:12px">{{ $o ? 'حفظ التعديلات' : 'حفظ الشحنة' }}</button>
    <div class="card" style="margin-top:12px"><h3>كيف يُحسب السعر</h3><div class="note" style="line-height:1.9">الوزن × (سعر الكيلو − التخفيض)، مع حد أدنى للشحنة حسب البلد. يظهر للعميل بالدينار بسعرين: كاش بسعر صرف <b class="num">{{ number_format($rates['cash'], 3) }}</b> ومصرف بسعر صرف <b class="num">{{ number_format($rates['bank'], 3) }}</b>، ويختار وقت التسليم، ويُدفع ويُسلَّم كبقية الطلبات.</div></div>
</div>
</div>
</form>
@endsection
@push('script')
<script>
(function () {
  const RATE = {{ $rates['cash'] }}, RC = {{ $rates['cash'] }}, RB = {{ $rates['bank'] }}, $ = id => document.getElementById(id), fmt = n => (Math.round(n * 100) / 100).toLocaleString('en-US');
  const cust = $('cust'), pk = $('pk'), kg = $('kg'), disc = $('disc');
  let pkTouched = pk.value !== '';
  const amt = $('amt'), cur = $('cur');
  let amtTouched = null; // أول مرة: لو السعر المحفوظ غير الحساب = انكتب باليد، نخلوه
  amt.addEventListener('input', () => { amtTouched = amt.value.trim() !== ''; calc(); });
  cur.addEventListener('change', () => { const v = parseFloat(amt.value); if (amtTouched && v) amt.value = Math.round((cur.value === 'lyd' ? v * RATE : v / RATE) * 100) / 100; calc(); });
  pk.addEventListener('input', () => pkTouched = true);
  const origin = () => document.querySelector('input[name=origin]:checked');
  const special = () => { try { return JSON.parse(cust.selectedOptions[0]?.dataset.sp || '{}') || {}; } catch (e) { return {}; } };
  // ✈️/🚢 سعر الكيلو حسب نوع الشحن المختار (من عنوان البلد متاعه)
  const modeKg = o => { try { const m = JSON.parse(o.dataset.modes || '{}') || {}; const t = document.getElementById('ptype'); return t && m[t.value] !== undefined ? m[t.value] : o.dataset.kg; } catch (e) { return o.dataset.kg; } };
  const setPk = () => { const o = origin(); if (!o) return; const sp = special(); const v = sp[o.value];
    $('spc').style.display = v ? 'inline-block' : 'none'; $('spc').textContent = v ? 'سعر خاص: ' + v + '$' : '';
    if (!pkTouched) pk.value = v || modeKg(o); calc(); };
  if (document.getElementById('ptype')) document.getElementById('ptype').addEventListener('change', () => { pkTouched = false; setPk(); });
  function calc() {
    const o = origin(), k = parseFloat(kg.value) || 0, d = parseFloat(disc.value) || 0;
    let p = parseFloat(pk.value) || 0; const tiers = JSON.parse(o?.dataset.tiers || '{}'); let tierTxt = '';
    Object.keys(tiers).forEach(f => { if (k >= parseFloat(f) && tiers[f] < p) { p = tiers[f]; tierTxt = ' (شريحة ' + f + '+ كغ)'; } });
    const net = Math.max(0, p - d);
    const usd = k > 0 ? Math.max(parseFloat(o?.dataset.min || 0), k * net) : 0;
    $('cKg').textContent = k ? fmt(k) + ' كغ' : '—'; $('cPk').textContent = fmt(p) + '$' + tierTxt; $('cDs').textContent = d ? '−' + fmt(d) + '$' : '0'; $('cNet').textContent = fmt(net) + '$';
    const exp = usd ? (cur.value === 'lyd' ? Math.round(usd * RATE * 100) / 100 : Math.round(usd * 100) / 100) : 0;
    if (amtTouched === null) amtTouched = amt.value.trim() !== '' && Math.abs((parseFloat(amt.value) || 0) - exp) > 0.05;
    if (!amtTouched) { amt.value = usd ? (cur.value === 'lyd' ? Math.round(usd * RATE * 100) / 100 : Math.round(usd * 100) / 100) : ''; }
    // 💵 كاش / 🏦 مصرف (السعر بالدولار × سعر صرف كل واحد)
    const usdNow = cur.value === 'lyd' ? (parseFloat(amt.value) || 0) / RC : (parseFloat(amt.value) || usd);
    $('rCash').textContent = '· صرف ' + RC; $('rBank').textContent = '· صرف ' + RB;
    $('cCash').textContent = usdNow ? fmt(usdNow * RC) + ' د.ل' : '—'; $('cBank').textContent = usdNow ? fmt(usdNow * RB) + ' د.ل' : '—';
    $('payWrap').style.display = cur.value === 'lyd' ? 'none' : '';
    $('cUsd').textContent = fmt(usd); $('cLyd').textContent = usd ? (d && k ? 'وفّر العميل ' + fmt(k * d) + '$' : '') : 'يُحسب عند كتابة الوزن';
  }
  cust.addEventListener('change', () => { pkTouched = false; setPk(); });
  document.querySelectorAll('input[name=origin]').forEach(r => r.addEventListener('change', () => { pkTouched = false; setPk(); }));
  [kg, pk, disc].forEach(i => i.addEventListener('input', calc));
  if (!pk.value) setPk(); else { setPk(); calc(); }
})();

/* 🚢 بحري ← CBM (متر مكعب) بدل الكيلو */
(function () {
  const pt = document.getElementById('ptype'); if (!pt) return;
  const T = { w: ['الوزن (كغ)', 'الحجم (CBM متر مكعب)'], p: ['سعر الكيلو ($)', 'سعر الـCBM ($)'], d: ['تخفيض على الكيلو ($)', 'تخفيض على الـCBM ($)'] };
  const upd = () => { const sea = (pt.value || '').includes('بحري'); document.querySelectorAll('[data-u]').forEach(el => el.textContent = T[el.dataset.u][sea ? 1 : 0]); };
  pt.addEventListener('change', upd); upd();
})();
</script>
@endpush
