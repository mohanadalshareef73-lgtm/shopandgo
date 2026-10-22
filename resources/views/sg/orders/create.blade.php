@extends('sg.layout')
@section('title', 'إضافة طلب')
@section('bodyClass', 'soft')
{{-- نفس فورم "إضافة طلب" القديم (نفس الخانات ونفس الترتيب) ويحفظ بنفس الكود القديم order.store --}}
@push('style')
<style>
.of { padding: 22px 24px; }
.of .row3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 16px; }
.of .fg label.l { font-size: 13.5px; color: var(--ink); font-weight: 700; margin-bottom: 7px; }
.of .inp { height: 46px; }
.of textarea.inp { height: 46px; min-height: 46px; }
.cs { position: relative; }
.cs .list { position: absolute; z-index: 20; right: 0; left: 0; top: 100%; margin-top: 4px; background: #fff; border-radius: 16px; box-shadow: 0 14px 34px rgba(22,26,46,.16); max-height: 260px; overflow: auto; padding: 6px; display: none; }
.cs.open .list { display: block; }
.cs .opt { padding: 9px 12px; border-radius: 10px; cursor: pointer; font-size: 14px; display: flex; justify-content: space-between; gap: 8px; }
.cs .opt:hover, .cs .opt.hi { background: #f3f4f8; }
.cs .opt small { color: var(--ink-3); font-family: InterNum; }
.cs .opt.new { color: var(--s1); font-weight: 700; }
.more-btn { display: flex; justify-content: center; margin: 20px 0 6px; }
.itemRow { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 12px; align-items: end; background: #f7f8fb; border-radius: 18px; padding: 12px 14px; margin-top: 10px; }
.actions2 { display: flex; flex-direction: column; align-items: center; gap: 12px; margin-top: 18px; }
@media (max-width: 900px) { .of .row3 { grid-template-columns: 1fr 1fr; } .itemRow { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .of .row3 { grid-template-columns: 1fr; } }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.orders.index') }}">الطلبات</a> › إضافة طلب</div>
<div class="top"><div><h1>إضافة طلب</h1><div class="sub">نفس فورم الطلب القديم</div></div><div class="acts"><a class="btn ghost" href="{{ route('sg.orders.index') }}">← رجوع للطلبات</a><button class="sg-burger">☰</button></div></div>

@can('shein-card-sell')<div style="display:inline-flex;background:#fff;border-radius:99px;padding:5px;gap:4px;margin-bottom:16px"><a style="padding:9px 18px;border-radius:99px;background:var(--brand);color:#fff;font-weight:700;font-size:14px">🛍 طلب مشتريات</a><a href="{{ route('sg.cards.sell') }}" style="padding:9px 18px;border-radius:99px;font-size:14px;color:var(--ink-2)">💳 بيع بطاقة SHEIN</a><a href="{{ route('sg.sitepay') }}" style="padding:9px 18px;border-radius:99px;font-size:14px;color:var(--ink-2)">🌐 دفع مواقع</a></div>@else<div style="margin-bottom:16px"><a class="btn ghost" href="{{ route('sg.sitepay') }}">🌐 إيصال دفع مواقع</a></div>@endcan
<form action="{{ route('order.store') }}" method="post" enctype="multipart/form-data" class="card of" id="of">
    @csrf
    <div class="row3">
        <div class="fg">
            <label class="l">العميل</label>
            <div class="cs" id="cs">
                <input type="hidden" name="customer_id" id="customer_id" value="{{ old('customer_id', 'new') }}">
                <input class="inp" id="csq" autocomplete="off" value="عميل جديد" placeholder="اكتب الاسم أو الكود أو الهاتف…">
                <div class="list" id="csl"></div>
            </div>
        </div>
        <div class="fg new_customer_fields"><label class="l">اسم العميل</label><input type="text" name="name" class="inp" placeholder="اسم العميل" value="{{ old('name', request('name')) }}"></div>
        <div class="fg new_customer_fields"><label class="l">رقم الهاتف</label><input type="text" name="phone_1" id="ph1" class="inp numr" placeholder="رقم الهاتف" value="{{ old('phone_1', request('phone')) }}" autocomplete="off"><div id="phWarn" hidden style="margin-top:6px;padding:8px 10px;border-radius:10px;background:#fdecea;color:#b3261e;font-size:13px;line-height:1.7"></div></div>

        <div class="fg"><label class="l">المنصة</label>
            <select name="platform_id" required class="inp"><option value="">-- اختر المنصة --</option>@foreach ($platforms as $platform)<option value="{{ $platform->id }}" @selected(old('platform_id') == $platform->id)>{{ $platform->name }}</option>@endforeach</select>
        </div>
        <div class="fg"><label class="l">الكمية</label><input type="number" name="qty" class="inp numr" required placeholder="الكمية" value="{{ old('qty') }}"></div>
        <div class="fg"><label class="l">اجمالي سعر البضاعة</label><input type="number" step="any" name="total_price" class="inp numr" required placeholder="اجمالي سعر البضاعة" value="{{ old('total_price') }}"></div>

        <div class="fg"><label class="l">القيمة المدفوعة</label><input type="number" step="any" name="paid_price" class="inp numr" required placeholder="القيمة المدفوعة" value="{{ old('paid_price', 0) }}"></div>
        <div class="fg"><label class="l">العملة</label>
            <select name="currency" required class="inp"><option selected value="dinar">دينار ليبي - د.ل</option><option value="dollar">دولار - $</option></select>
        </div>
        <div class="fg"><label class="l">طريقة الدفع</label>
            <select name="payment_id" required class="inp">@foreach ($payments as $payment)<option value="{{ $payment->id }}" @selected($payment->id == \App\Services\AppSettings::cashPaymentId())>{{ $payment->name }}</option>@endforeach</select>
        </div>
    </div>

    <div class="more-btn"><button type="button" class="btn ghost" id="moreBtn">إظهار المزيد من المعلومات</button></div>

    <div class="row3" id="moreFields" style="display:none">
        <div class="fg"><label class="l">بيانات الرحلة</label><input type="text" name="trip_details" class="inp" placeholder="بيانات الرحلة"></div>
        <div class="fg new_customer_fields"><label class="l">رقم الهاتف 2 (إختياري)</label><input type="text" name="phone_2" class="inp numr" placeholder="رقم الهاتف 2 (إختياري)"></div>
        <div class="fg new_customer_fields"><label class="l">كود العميل (إختياري)</label><input type="text" name="code" class="inp numr" placeholder="كود العميل (إختياري)" data-code-check data-phone="#of input[name=phone_1]" autocomplete="off"></div>
        <div class="fg new_customer_fields"><label class="l">المدينة <b style="color:var(--down)">*</b></label><input type="text" name="city" class="inp" list="lyCities" value="{{ old('city', 'بنغازي') }}" placeholder="المدينة"></div>
        <div class="fg new_customer_fields"><label class="l">المنطقة <b style="color:var(--down)">*</b></label><input type="text" name="area" class="inp" value="{{ old('area') }}" placeholder="مثلاً الكيش، الهواري…"></div>
        <div class="fg new_customer_fields"><label class="l">العنوان بالتفصيل (إختياري)</label><input type="text" name="address" class="inp" placeholder="العنوان (إختياري)"></div>@include('sg.partials.cities')
        <div class="fg"><label class="l">نوع الطرد</label><input type="text" name="order_type" class="inp" placeholder="نوع الطرد"></div>
        <div class="fg"><label class="l">عدد الطرود (إختياري)</label><input type="number" name="bags_num" class="inp numr" placeholder="عدد الطرود"></div>
        <div class="fg"><label class="l">الكمية في الطرود (إختياري)</label><input type="number" name="bags_qty" class="inp numr" placeholder="الكمية في الطرود"></div>
        <div class="fg"><label class="l">اجمالي سعر الشحن (إختياري)</label><input type="number" step="any" value="0" name="ship_price" class="inp numr" placeholder="سعر الشحن"></div>
        <div class="fg"><label class="l">الوزن بالكيلو (إختياري)</label><input type="number" step="any" name="kilo" class="inp numr" placeholder="الوزن بالكيلو"></div>
        <div class="fg"><label class="l">رقم التخزين (إختياري)</label><input type="number" name="storage_num" class="inp numr" placeholder="رقم التخزين"></div>
        <div class="fg" style="grid-column:span 2"><label class="l">ملاحظات (إختياري)</label><textarea name="note" class="inp" placeholder="ملاحظات (إختياري)"></textarea></div>
    </div>

    <div id="addedItems"></div>
    <div class="actions2">
        <button type="button" class="btn ghost" id="addItem" style="color:var(--s1)">+ اضافة المنتج</button>
        @include('sg.partials.dup-warn')
        <button type="submit" class="btn lg" style="min-width:260px">حفظ الطلب</button>
    </div>
</form>
@include('sg.partials.code-check')
@endsection
@push('script')
<script>
(function () {
  const C = @json($customers->map(fn ($c) => ['id' => $c->id, 't' => $c->name . ' - ' . $c->code . ' - ' . $c->phone_1]));
  const $ = id => document.getElementById(id);
  const cs = $('cs'), q = $('csq'), list = $('csl'), hid = $('customer_id');
  function toggleCustomerFields() { document.querySelectorAll('.new_customer_fields').forEach(el => el.style.display = hid.value === 'new' ? '' : 'none'); }
  function render() {
    const t = q.value.trim().toLowerCase();
    const rows = (t && t !== 'عميل جديد' ? C.filter(c => c.t.toLowerCase().includes(t)) : C).slice(0, 40);
    list.innerHTML = '<div class="opt new" data-id="new">+ عميل جديد</div>' + rows.map(c => { const p = c.t.split(' - '); return `<div class="opt" data-id="${c.id}"><span>${p[0]}</span><small>${p.slice(1).join(' · ')}</small></div>`; }).join('');
  }
  q.addEventListener('focus', () => { if (q.value === 'عميل جديد') q.select(); render(); cs.classList.add('open'); });
  q.addEventListener('input', () => { render(); cs.classList.add('open'); });
  list.addEventListener('mousedown', e => {
    const o = e.target.closest('.opt'); if (!o) return;
    e.preventDefault(); hid.value = o.dataset.id;
    q.value = o.dataset.id === 'new' ? 'عميل جديد' : C.find(c => c.id == o.dataset.id).t;
    cs.classList.remove('open'); toggleCustomerFields();
  });
  q.addEventListener('blur', () => setTimeout(() => cs.classList.remove('open'), 120));
  if (hid.value !== 'new') { const pc = C.find(c => c.id == hid.value); if (pc) q.value = pc.t; }
  toggleCustomerFields();

  // رقم موجود؟ ننبهك وأنت تكتب
  const ph = $('ph1'), warn = $('phWarn'); let pt;
  function checkPhone() {
    clearTimeout(pt);
    pt = setTimeout(async () => {
      const v = ph.value.replace(/\D/g, '');
      if (hid.value !== 'new' || v.length < 7) { warn.hidden = true; ph.style.borderColor = ''; return; }
      try {
        const r = await (await fetch(@json(route('sg.orders.lookup')) + '?phone=' + encodeURIComponent(v))).json();
        if (!r) { warn.hidden = true; ph.style.borderColor = ''; return; }
        ph.style.borderColor = '#d93025';
        warn.innerHTML = `⚠ الرقم هذا موجود باسم <b>${r.name}</b> (كود <b>${r.code}</b>)${r.due > 0 ? ' · عليه ' + r.due + ' د.ل' : ''}<br>
          <a href="#" id="phPick" style="color:#b3261e;text-decoration:underline;font-weight:700">لو هو نفسه اضغط هنا نختاره لك</a> · أو كمّل عادي`;
        warn.hidden = false;
        $('phPick').onclick = e => { e.preventDefault(); hid.value = r.id; const c = C.find(x => x.id == r.id); q.value = c ? c.t : (r.name + ' - ' + r.code); warn.hidden = true; ph.style.borderColor = ''; toggleCustomerFields(); };
      } catch (e) {}
    }, 350);
  }
  ph.addEventListener('input', checkPhone);
  if (ph.value) checkPhone();

  $('moreBtn').onclick = () => { const m = $('moreFields'); const hidden = m.style.display === 'none'; m.style.display = hidden ? '' : 'none'; $('moreBtn').textContent = hidden ? 'إخفاء المعلومات الإضافية' : 'إظهار المزيد من المعلومات'; };

  // المنتجات: نفس القديم، والكمية والإجمالي يتحسبوا من المنتجات
  let n = 0;
  function calculateTotals() {
    let qty = 0, price = 0;
    document.querySelectorAll('input[name="items[quantity][]"]').forEach(i => { qty += parseFloat(i.value) || 0; });
    document.querySelectorAll('input[name="items[price][]"]').forEach(i => { price += parseFloat(i.value) || 0; });
    if (document.querySelectorAll('.itemRow').length) { document.querySelector('input[name=qty]').value = qty; document.querySelector('input[name=total_price]').value = price.toFixed(2); }
  }
  $('addItem').onclick = () => {
    n++;
    const d = document.createElement('div'); d.className = 'itemRow';
    d.innerHTML = `<div class="fg"><label class="l">اسم المنتج ${n}</label><input type="text" name="items[name][]" required class="inp" placeholder="اسم المنتج"></div>
      <div class="fg"><label class="l">الكمية</label><input type="number" name="items[quantity][]" required class="inp numr" placeholder="الكمية"></div>
      <div class="fg"><label class="l">إجمالي السعر</label><input type="number" step="any" name="items[price][]" required class="inp numr" placeholder="إجمالي السعر"></div>
      <button type="button" class="btn red">حذف المنتج</button>`;
    d.querySelectorAll('input').forEach(i => i.addEventListener('input', calculateTotals));
    d.querySelector('button').onclick = () => { d.remove(); calculateTotals(); };
    $('addedItems').appendChild(d); d.querySelector('input').focus();
  };
})();
</script>
@endpush
