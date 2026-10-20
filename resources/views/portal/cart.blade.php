@include('portal.partials.head', ['title' => '🛒 سلة ' . $cart->retailer_label . ' #' . $cart->id])
@php
    $locked = in_array($cart->portal_status, ['ordered', 'confirmed']);
    // ألوان قريبة من موقع المتجر نفسه
    $th = ['shein' => ['#000000', '#fa6338', '#f6f6f6', 'SHEIN'], 'iherb' => ['#458500', '#ff7a00', '#f4f8ee', 'iHerb'], 'amazon' => ['#131921', '#ff9900', '#eaeded', 'amazon']][$cart->retailer] ?? ['#262c4a', '#1d8a5f', '#f2f3f7', $cart->retailer_label];
@endphp
<style>
:root { --st: {{ $th[0] }}; --acc: {{ $th[1] }}; --bgx: {{ $th[2] }}; }
body { background: var(--bgx); }
.store { background: var(--st); color: #fff; border-radius: 0 0 18px 18px; margin: -16px -16px 14px; padding: 14px 16px; display: flex; justify-content: space-between; align-items: center; }
.store b { font-size: 22px; letter-spacing: 3px; font-family: Arial, sans-serif; }
.grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
@media (min-width: 640px) { .grid { grid-template-columns: repeat(3, 1fr); } }
.pc { background: #fff; border-radius: 4px; overflow: hidden; display: flex; flex-direction: column; position: relative; }
.pc img { width: 100%; aspect-ratio: 3/4; object-fit: cover; background: #eee; display: block; }
.pc .bd { padding: 8px 10px 10px; display: flex; flex-direction: column; gap: 4px; flex: 1; }
.pc .nm { font-size: 12.5px; line-height: 1.45; height: 2.9em; overflow: hidden; color: #222; }
.pc .vr { font-size: 11.5px; color: #767676; background: #f6f6f6; padding: 2px 6px; border-radius: 3px; align-self: flex-start; }
.pc .pr { font-weight: 800; font-size: 15px; color: var(--acc); }
.pc.off { opacity: .45; } .pc.off::after { content: 'غير متوفر'; position: absolute; top: 8px; right: 8px; background: #222; color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 3px; }
.qt { display: flex; align-items: center; justify-content: space-between; border: 1px solid #e5e5e5; border-radius: 3px; margin-top: auto; }
.qt button { width: 36px; height: 32px; border: 0; background: #fff; font-size: 18px; cursor: pointer; }
.qt b { font-size: 15px; }
.qt.zero { opacity: .6; }
.bar { position: fixed; bottom: 0; right: 0; left: 0; background: #fff; border-top: 1px solid #e5e5e5; padding: 12px 16px calc(12px + env(safe-area-inset-bottom)); box-shadow: 0 -4px 14px rgba(0,0,0,.06); }
.bar .in { max-width: 720px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.bar .t { font-size: 22px; font-weight: 800; color: var(--acc); }
.bar .btn { background: var(--st); }
.pay { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-top: 8px; }
.pay label { border: 1.5px solid #dfe2ea; border-radius: 10px; padding: 10px; cursor: pointer; font-size: 13.5px; text-align: center; }
.pay input { display: none; } .pay input:checked + span { color: var(--st); font-weight: 800; } .pay label:has(input:checked) { border-color: var(--st); background: #fafafa; }
</style>
<div class="store"><b>{{ $th[3] }}</b><span style="font-size:13px;opacity:.85">سلة #{{ $cart->id }}</span></div>

<div id="wait" class="card" style="text-align:center;{{ $d['status'] === 'succeeded' ? 'display:none' : '' }}">
    @if (in_array($cart->status, ['failed', 'expired', 'cancelled']))
        <div style="font-size:32px">⚠️</div><b>ما قدرناش نحسبو السلة هذي</b><div class="note">تأكد إنه رابط مشاركة السلة، أو ابعتهولنا على الواتساب.</div>
        <a class="btn ghost" style="margin-top:10px" href="{{ route('portal.carts') }}">رجوع</a>
    @else
        <div style="font-size:32px">⏳</div><b>نحسبو في سلتك…</b>
        <div style="font-size:34px;font-weight:800;color:var(--st);margin:8px 0" class="num" id="cd">--:--</div>
        <div class="note" id="cdn">باقي تقريباً عشان يوصلك السعر · خلي الصفحة مفتوحة</div>
        <div style="height:6px;background:#eceef3;border-radius:6px;overflow:hidden;margin-top:10px"><div id="cdb" style="height:100%;width:0;background:var(--acc);transition:width 1s linear"></div></div>
    @endif
</div>

@if ($locked)
    <div class="card" style="border:2px solid #1d8a5f">
        <b style="font-size:16px">{{ $cart->portal_status === 'confirmed' ? '✓ تم تأكيد حجزك' : '✓ وصلنا طلبك' }}</b>
        @if ($cart->portal_status !== 'confirmed' && !empty($guest))
            <div class="note" style="margin-top:6px;line-height:1.8">📲 أول ما نأكدو طلبك يجيك <b>الإيصال على الواتساب</b>، و<b>ينفتحلك حساب</b> تتابع منه طلبك.</div>
        @endif
        @if ($cart->portal_status !== 'confirmed')
            <div style="margin-top:6px;line-height:1.8">@if ($cart->pay_method === 'cash_courier')اخترت <b>كاش عند المندوب</b> — بنتواصلو معاك لتأكيد الحجز.@else لو بتدفع <b>بتحويل</b>، ابعتلنا صورة الإيصال من هني تحت، و<b>خلال 24 ساعة يتم تأكيد طلبك</b>.@php [$dM, $dP] = \App\Http\Controllers\Portal\CartsController::deposit(); @endphp @if ($dM > 0 && (float) ($d['total'] ?? 0) >= $dM)<br>العربون المطلوب: <b class="num">{{ number_format(ceil((float) $d['total'] * $dP / 100)) }}</b> د.ل ({{ $dP }}%)@endif @endif</div>
        @endif
        <div style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap"><a class="btn ghost" href="{{ route('portal.cart.pdf', $cart->id) }}" target="_blank">📄 ملف الطلب PDF</a></div>
    </div>
    @if ($cart->portal_status !== 'confirmed' && $cart->pay_method !== 'cash_courier')
    <div class="card">
        <b>🧾 صورة إيصال التحويل</b> @if ($cart->receipt_path)<span class="note">· ✓ وصلنا إيصال، تقدر تبدّله</span>@endif
        <form method="post" action="{{ route('portal.cart.receipt', $cart->id) }}" enctype="multipart/form-data" style="margin-top:8px;display:flex;gap:8px;flex-wrap:wrap">
            @csrf <input type="file" name="receipt" accept="image/*,application/pdf" class="inp" style="flex:1" required><button class="btn">إرسال</button>
        </form>
    </div>
    @endif
@endif

@if (!empty($guest) && !$locked)
<div class="card" style="border:2px dashed #1d8a5f;background:#f3fbf7">
    <b>⚡ احجز طول — من غير حساب</b>
    <div class="note" style="margin:4px 0 8px;line-height:1.8">عدّل الكميات، اكتب اسمك ورقمك تحت، واختار تحويل ولا مندوب. وقت نأكدو طلبك يجيك الإيصال على الواتساب و<b>ينفتحلك حساب أوتوماتيك</b>.</div>
    <a class="note" href="{{ route('customer.login') }}" style="text-decoration:underline">🔑 عندك حساب؟ ادخل</a>
</div>
@php session(['url.intended' => route('portal.cart', $cart->id)]); @endphp
@endif
<form method="post" action="{{ route('portal.cart.order', $cart->id) }}" enctype="multipart/form-data" id="of" style="{{ $d['status'] === 'succeeded' ? '' : 'display:none' }}">
    @csrf
    <div class="grid" id="items"></div>
    @if (!$locked)
    @if (!empty($guest))
    @php
        $gPh = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) $cart->whatsapp_customer_phone));
        $gNm = optional($cart->incomingMessage)->name; $gNm = in_array($gNm, ['زائر', null, ''], true) ? '' : $gNm;
    @endphp
    <div class="card" style="margin-top:12px">
        <b>👤 بياناتك</b>
        @if ($errors->any())<div class="flash e" style="margin:8px 0 0">{{ $errors->first() }}</div>@endif
        <div style="display:grid;gap:8px;margin-top:8px">
            <input name="g_name" class="inp" placeholder="اسمك الكامل" value="{{ old('g_name', $gNm) }}" required maxlength="120">
            <input name="g_phone" class="inp num" inputmode="tel" placeholder="رقم هاتفك (عليه واتساب) — مثال 0912345678" value="{{ old('g_phone', $gPh) }}" required maxlength="20">
            <div id="gAddrBox" style="display:none;gap:8px;flex-direction:column">
                <input name="g_address" id="gAddr" class="inp" placeholder="📍 عنوانك (المدينة والمنطقة وأقرب معلم)" value="{{ old('g_address') }}" maxlength="200">
                <input name="g_location" class="inp" placeholder="رابط موقعك على الخريطة (اختياري)" value="{{ old('g_location') }}" maxlength="500">
            </div>
        </div>
    </div>
    @endif
    <div class="card" style="margin-top:12px">
        <b>كيف تبي تدفع؟</b>
        <div class="pay">
            <label><input type="radio" name="pay_method" value="transfer" checked><span>🏦 تحويل مصرفي<br><small class="note">عربون وترفع الإيصال</small></span></label>
            <label id="cashOpt"><input type="radio" name="pay_method" value="cash_courier"><span>🚚 كاش عند المندوب<br><small class="note">تدفع لما يجيك</small></span></label>
        </div>
        <div id="depNote" class="flash w" style="margin:10px 0 0;display:none"></div>
        @php
            $cFee = (float) \App\Services\AppSettings::get('courier_default_fee', 10); $cPct = !empty(optional($c)->trusted) ? 0 : \App\Http\Controllers\Portal\CartsController::deposit()[1];
            $bk = ['name' => \App\Services\AppSettings::get('transfer_bank_name'), 'holder' => \App\Services\AppSettings::get('transfer_holder'), 'acc' => \App\Services\AppSettings::get('transfer_account')];
        @endphp
        <div id="feeBox" style="margin-top:12px">
            <div id="bankBox" style="display:none;background:#f4f6fb;border-radius:12px;padding:12px;margin-bottom:10px;line-height:2">
                <b>🏦 حوّل على الحساب هذا:</b>
                @if ($bk['acc'])<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">رقم الحساب: <b class="num" id="bkAcc" style="font-size:17px">{{ $bk['acc'] }}</b><button type="button" class="btn ghost" style="padding:4px 12px;font-size:13px" onclick="navigator.clipboard.writeText(document.getElementById('bkAcc').textContent.trim());this.textContent='✓ تنسخ'">⧉ نسخ</button></div>@endif
                @if ($bk['holder'])<div>اسم صاحب الحساب: <b>{{ $bk['holder'] }}</b></div>@endif
                @if ($bk['name'])<div>المصرف: <b>{{ $bk['name'] }}</b></div>@endif
                @if (!$bk['acc'])<div class="note">بيانات الحساب يبعتهالك موظفنا.</div>@endif
            </div>
            <label style="display:block;font-weight:700;margin-bottom:6px">💵 كم تبي تدفع توا؟ @if ($cPct > 0)<span class="note">(أقل شي {{ $cPct }}% من قيمة الطلب)</span>@else<span class="chip" style="background:#e6f6ee;color:#0f6b4c">✅ حسابك موثوق — تقدر تكتب 0 وتأكد بدون عربون</span>@endif</label>
            <input name="courier_pay" id="cpIn" class="inp num" inputmode="decimal" style="font-size:20px;font-weight:800">
            <div id="cpErr" class="flash e" style="margin:8px 0 0;display:none"></div>
            <div id="rcBox" style="display:none;margin-top:10px">
                <label style="display:block;font-weight:700;margin-bottom:6px">🧾 صورة إيصال التحويل أو ملف PDF @if (false)<span class="note"></span>@else<span style="color:#b3261e">*</span>@endif</label>
                <input type="file" name="receipt" id="rcIn" accept="image/*,application/pdf" class="inp">
            </div>
            <div id="feeNote" class="flash s" style="margin:8px 0 0;line-height:1.9"></div>
        </div>
        <script>
        (function () {
          const GUEST = {{ !empty($guest) ? 'true' : 'false' }}, FEE = {{ $cFee }}, PCT = {{ $cPct }}, inp = document.getElementById('cpIn'), err = document.getElementById('cpErr'), n = document.getElementById('feeNote'), rc = document.getElementById('rcIn');
          let lastTot = -1;
          const fmt = v => (Math.round(v * 100) / 100).toLocaleString('en-US');
          const upd = () => { const m = (document.querySelector('input[name=pay_method]:checked') || {}).value, cour = m === 'cash_courier', tr = m === 'transfer';
            const tot = parseFloat(String((document.getElementById('tt') || {}).textContent || '0').replace(/,/g, '')) || 0, min = Math.ceil(tot * PCT / 100);
            document.getElementById('bankBox').style.display = tr ? '' : 'none'; document.getElementById('rcBox').style.display = tr ? '' : 'none'; rc.required = tr && (parseFloat(inp.value) || 0) > 0;
            const ga = document.getElementById('gAddrBox'); if (ga) { ga.style.display = cour ? 'flex' : 'none'; document.getElementById('gAddr').required = cour; }
            if (tot !== lastTot) { if (!inp.value || parseFloat(inp.value) > tot) inp.value = Math.round(tot); lastTot = tot; }
            const pay = Math.min(parseFloat(inp.value) || 0, tot), low = pay + 0.009 < min;
            err.style.display = low ? '' : 'none'; err.innerHTML = 'المبلغ شوية 🙏 أقل شي <b class="num">' + fmt(min) + '</b> د.ل';
            const go = document.getElementById('go'); if (go) go.disabled = low || !tot;
            n.innerHTML = '🛍 قيمة الطلب: <b class="num">' + fmt(tot) + '</b> د.ل <small style="color:#b45309">(الشحن غير محسوب)</small><br>✅ تدفع توا: <b class="num">' + fmt(pay) + '</b> د.ل · الباقي وقت الاستلام: <b class="num">' + fmt(Math.max(0, tot - pay)) + '</b>'
              + (cour ? '<br>💵 <b>اللي تعطيه للمندوب: <span class="num">' + fmt(pay) + '</span> د.ل</b>' : '<br>🏦 <b>حوّل <span class="num">' + fmt(pay) + '</span> د.ل وارفع صورة الإيصال</b>'); };
          document.querySelectorAll('input[name=pay_method]').forEach(i => i.addEventListener('change', upd));
          inp.addEventListener('input', upd); setInterval(upd, 1500); upd();
        })();
        </script>
    </div>
    @endif
</form>

<div class="bar" id="bar" style="{{ $d['status'] === 'succeeded' ? '' : 'display:none' }}">
    <div class="in">
        <div><div class="note"><span class="num" id="tq">0</span> قطعة</div><div class="t"><span class="num" id="tt">0</span> <small style="font-size:13px;font-weight:400;color:#767676">د.ل</small></div><div style="font-size:11.5px;color:#b45309;font-weight:700">🚚 الشحن غير محسوب من ضمن هذه التكلفة</div></div>
        @if (!$locked)@if (!empty($guest))<button class="btn green" form="of" id="go" onclick="return confirm('نأكدو الطلب بالكميات هذي؟')">✓ أكد الطلب</button>@else<button class="btn" form="of" id="go" onclick="return confirm('نأكدو الطلب بالكميات هذي؟')">✓ أكد الطلب</button>@endif @endif
    </div>
</div>

<script>
(function () {
  let D = @json($d);
  const locked = @json($locked), img = i => @json(route('portal.cart.image', [$cart->id, 0])).replace(/\/0$/, '/' + i);
  const fmt = n => (Math.round(n * 100) / 100).toLocaleString('en-US');
  function render() {
    const box = document.getElementById('items');
    box.innerHTML = D.items.map(x => `<div class="pc ${x.ok ? '' : 'off'}">
      <img src="${img(x.i)}" loading="lazy" onerror="this.style.visibility='hidden'">
      <div class="bd"><div class="nm">${x.name || ''}</div>${x.variant ? `<div class="vr">${x.variant}</div>` : ''}
      <div class="pr">${x.ok && x.unit !== null ? '<span class="num">' + fmt(x.unit) + '</span> د.ل' : ''}</div>
      <div class="qt ${x.qty ? '' : 'zero'}">${x.ok && !locked && !x.fixed ? `<button type="button" data-i="${x.i}" data-d="1">+</button>` : '<span></span>'}<b class="num" id="q${x.i}">${x.qty}</b>${x.ok && !locked && !x.fixed ? `<button type="button" data-i="${x.i}" data-d="-1">−</button>` : '<span></span>'}</div>
      <input type="hidden" name="qty[${x.i}]" id="h${x.i}" value="${x.qty}"></div></div>`).join('');
    box.querySelectorAll('.qt button').forEach(b => b.onclick = () => { const x = D.items.find(y => y.i == b.dataset.i); x.qty = Math.max(0, Math.min(999, x.qty + (+b.dataset.d))); document.getElementById('q' + x.i).textContent = x.qty; document.getElementById('h' + x.i).value = x.qty; b.parentElement.classList.toggle('zero', !x.qty); sum(); });
    sum();
  }
  function sum() {
    const ok = D.items.filter(x => x.ok);
    document.getElementById('tq').textContent = ok.reduce((a, x) => a + x.qty, 0);
    const fixed = ok.some(x => x.unit === null);
    document.getElementById('tt').textContent = fixed || locked ? (D.total ?? '—') : Math.round(ok.reduce((a, x) => a + x.unit * x.qty, 0)).toLocaleString('en-US');
    const g = document.getElementById('go'); if (g) g.disabled = !ok.some(x => x.qty > 0);
    // عربون إجباري للسلات الكبيرة
    const DM = {{ \App\Http\Controllers\Portal\CartsController::deposit()[0] }}, DP = {{ \App\Http\Controllers\Portal\CartsController::deposit()[1] }};
    const tot = parseFloat(String(document.getElementById('tt').textContent).replace(/,/g, '')) || 0, dn = document.getElementById('depNote'), co = document.getElementById('cashOpt');
    if (dn && co) { const big = false; // أي قيمة يقدر يطلب مندوب dn.style.display = big ? '' : 'none'; co.style.opacity = big ? .4 : 1; co.querySelector('input').disabled = big;
      if (big) { dn.innerHTML = 'طلبك فوق <b class="num">' + DM.toLocaleString('en-US') + '</b> د.ل — لازم <b>عربون ' + DP + '%</b> = <b class="num">' + Math.ceil(tot * DP / 100).toLocaleString('en-US') + '</b> د.ل بتحويل، والباقي وقت الاستلام'; const t = document.querySelector('input[name=pay_method][value=transfer]'); if (t) t.checked = true; } }
  }
  // العد التنازلي
  (function () {
    const cd = document.getElementById('cd'); if (!cd || D.status === 'succeeded') return;
    const start = new Date(@json($cart->created_at->toIso8601String())).getTime(), eta = new Date(D.eta).getTime(), max = new Date(D.max).getTime();
    const tick = () => {
      const now = Date.now(); let target = now < eta ? eta : max, left = Math.max(0, Math.round((target - now) / 1000));
      cd.textContent = String(Math.floor(left / 60)).padStart(2, '0') + ':' + String(left % 60).padStart(2, '0');
      document.getElementById('cdn').textContent = now < eta ? 'باقي تقريباً عشان يوصلك السعر · خلي الصفحة مفتوحة' : 'السلة كبيرة شوية · الحد الأقصى';
      document.getElementById('cdb').style.width = Math.min(100, (now - start) / ((now < eta ? eta : max) - start) * 100) + '%';
    };
    tick(); setInterval(tick, 1000);
  })();
  if (D.status === 'succeeded') render();
  else if (!['failed', 'expired', 'cancelled'].includes(D.status)) {
    const t = setInterval(async () => {
      try { const r = await (await fetch(@json(route('portal.cart.poll', $cart->id)))).json();
        if (r.status === 'succeeded' && r.items.length) { clearInterval(t); D = r; document.getElementById('wait').style.display = 'none'; document.getElementById('of').style.display = ''; document.getElementById('bar').style.display = ''; render(); }
        else if (['failed', 'expired', 'cancelled'].includes(r.status)) { clearInterval(t); location.reload(); } } catch (e) {}
    }, 6000);
  }
})();
</script>
</div></body></html>
