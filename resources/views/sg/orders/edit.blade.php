@extends('sg.layout')
@section('title', 'تعديل ' . $order->code)
@section('bodyClass', 'soft')
{{-- نفس خانات "تعديل الطلب" القديم، ويحفظ بنفس الكود القديم order.update --}}
@push('style')
<style>
.of { padding: 22px 24px; }
.of .row3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px 16px; }
.of .fg label.l { font-size: 13.5px; color: var(--ink); font-weight: 700; margin-bottom: 7px; }
.of .inp { height: 46px; } .of textarea.inp { height: 46px; min-height: 46px; }
.itemRow { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 12px; align-items: end; background: #f7f8fb; border-radius: 18px; padding: 12px 14px; margin-top: 10px; }
.actions2 { display: flex; flex-direction: column; align-items: center; gap: 12px; margin-top: 18px; }
.who2 { display: flex; gap: 12px; align-items: center; background: #f7f8fb; border-radius: 16px; padding: 12px 14px; margin-bottom: 16px; }
@media (max-width: 900px) { .of .row3 { grid-template-columns: 1fr 1fr; } .itemRow { grid-template-columns: 1fr 1fr; } }
@media (max-width: 560px) { .of .row3 { grid-template-columns: 1fr; } }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.orders.index') }}">الطلبات</a> › <a href="{{ route('sg.orders.show', $order) }}">{{ $order->code }}</a> › تعديل</div>
<div class="top"><div><h1>تعديل الإيصال <span class="code">{{ $order->code }}</span></h1><div class="sub">نفس خانات التعديل القديم، بالشكل الجديد</div></div><div class="acts"><a class="btn ghost" href="{{ route('sg.orders.show', $order) }}">← رجوع للإيصال</a><button class="sg-burger">☰</button></div></div>

<form action="{{ route('order.update', $order->id) }}" method="post" enctype="multipart/form-data" class="card of" id="ef">
    @method('PUT')
    @csrf
    <div class="who2"><span class="av">{{ mb_substr(optional($order->customer)->name, 0, 1) }}</span><div style="flex:1"><b>{{ optional($order->customer)->name }}</b><div class="note num">{{ optional($order->customer)->code }} · {{ optional($order->customer)->phone_1 }}</div></div><span class="note">العميل لا يتغير من هنا</span></div>
    <div class="row3">
        <div class="fg"><label class="l">بيانات الرحلة</label><input type="text" name="trip_details" value="{{ $order->trip_details }}" class="inp" placeholder="بيانات الرحلة"></div>
        <div class="fg"><label class="l">المنصة</label><select name="platform_id" required class="inp"><option value="">-- اختر المنصة --</option>@foreach ($platforms as $platform)<option value="{{ $platform->id }}" @selected($order->platform_id == $platform->id)>{{ $platform->name }}</option>@endforeach</select></div>
        <div class="fg"><label class="l">العملة</label><select name="currency" required class="inp">@foreach (array_merge(['dinar' => 'دينار ليبي - د.ل', 'dollar' => 'دولار - $'], $order->currency === 'euro' ? ['euro' => 'يورو - €'] : []) as $k => $v)<option value="{{ $k }}" @selected(($order->currency ?: 'dinar') === $k)>{{ $v }}</option>@endforeach</select></div>

        <div class="fg"><label class="l">الكمية</label><input type="number" name="qty" value="{{ $order->qty }}" class="inp numr" required></div>
        <div class="fg"><label class="l">اجمالي سعر البضاعة</label><input type="number" step="any" name="total_price" value="{{ $order->total_price }}" class="inp numr" required></div>
        <div class="fg"><label class="l">نوع الطرد</label><input type="text" name="order_type" value="{{ $order->order_type }}" class="inp" placeholder="نوع الطرد"></div>

        <div class="fg"><label class="l">عدد الطرود (إختياري)</label><input type="number" name="bags_num" value="{{ $order->bags_num }}" class="inp numr"></div>
        <div class="fg"><label class="l">الكمية في الطرود (إختياري)</label><input type="number" name="bags_qty" value="{{ $order->bags_qty }}" class="inp numr"></div>
        <div class="fg"><label class="l">اجمالي سعر الشحن (إختياري)</label><input type="number" step="any" name="ship_price" value="{{ $order->ship_price }}" class="inp numr"></div>

        <div class="fg"><label class="l">الوزن بالكيلو (إختياري)</label><input type="number" step="any" name="kilo" value="{{ $order->kilo }}" class="inp numr"></div>
        <div class="fg"><label class="l">رقم التخزين (إختياري)</label><input type="number" name="storage_num" value="{{ $order->storage_num }}" class="inp numr"></div>
        <div class="fg"><label class="l">ملاحظات (إختياري)</label><textarea name="note" class="inp">{{ $order->note }}</textarea></div>
    </div>

    <div id="addedItems">
        @foreach ($order->items as $item)
            <div class="itemRow">
                <div class="fg"><label class="l">اسم المنتج {{ $loop->iteration }}</label><input type="text" name="items[name][]" required value="{{ $item->name }}" class="inp"></div>
                <div class="fg"><label class="l">الكمية</label><input type="number" name="items[quantity][]" required value="{{ $item->quantity }}" class="inp numr"></div>
                <div class="fg"><label class="l">إجمالي السعر</label><input type="number" step="any" name="items[price][]" required value="{{ $item->price }}" class="inp numr"></div>
                <button type="button" class="btn red rm">حذف المنتج</button>
            </div>
        @endforeach
    </div>
    <div class="actions2">
        <button type="button" class="btn ghost" id="addItem" style="color:var(--s1)">+ اضافة المنتج</button>
        <button type="submit" class="btn lg" style="min-width:260px">حفظ التعديلات</button>
    </div>
</form>
@endsection
@push('script')
<script>
(function () {
  const box = document.getElementById('addedItems');
  let n = box.querySelectorAll('.itemRow').length;
  const calc = () => {
    if (!box.querySelectorAll('.itemRow').length) return;
    let q = 0, p = 0;
    box.querySelectorAll('input[name="items[quantity][]"]').forEach(i => q += parseFloat(i.value) || 0);
    box.querySelectorAll('input[name="items[price][]"]').forEach(i => p += parseFloat(i.value) || 0);
    document.querySelector('#ef input[name=qty]').value = q; document.querySelector('#ef input[name=total_price]').value = p.toFixed(2);
  };
  const bind = d => { d.querySelectorAll('input').forEach(i => i.addEventListener('input', calc)); d.querySelector('.rm').onclick = () => { d.remove(); calc(); }; };
  box.querySelectorAll('.itemRow').forEach(bind);
  document.getElementById('addItem').onclick = () => {
    n++; const d = document.createElement('div'); d.className = 'itemRow';
    d.innerHTML = `<div class="fg"><label class="l">اسم المنتج ${n}</label><input type="text" name="items[name][]" required class="inp" placeholder="اسم المنتج"></div><div class="fg"><label class="l">الكمية</label><input type="number" name="items[quantity][]" required class="inp numr"></div><div class="fg"><label class="l">إجمالي السعر</label><input type="number" step="any" name="items[price][]" required class="inp numr"></div><button type="button" class="btn red rm">حذف المنتج</button>`;
    box.appendChild(d); bind(d); d.querySelector('input').focus();
  };
})();
</script>
@endpush
