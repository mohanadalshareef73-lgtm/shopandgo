@extends('sg.layout')
@section('title', 'أسعار الشحن')
@section('bodyClass', 'soft')
@push('style')
<style>
.prt td { vertical-align: top; padding: 12px 10px !important; }
.prt .inp { height: 40px; }
.prt input.n2 { width: 100px; font-family: InterNum; font-weight: 800; direction: ltr; text-align: right; }
.prt textarea { width: 100%; min-height: 104px; font-family: InterNum, Somar; direction: ltr; text-align: left; font-size: 13px; line-height: 1.6; }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.shipping') }}">الشحن</a> › أسعار الشحن</div>
<div class="top"><div><h1>أسعار الشحن والمواقع</h1><div class="sub">سعر الكيلو لكل بلد، وعنوان كل مخزن، والأسعار الخاصة، وأسعار المواقع التي تظهر للعملاء</div></div><div class="acts"><button class="sg-burger">☰</button></div></div>
<form method="post" action="{{ route('sg.shipping.prices.save') }}">
@csrf
<div class="card tw mb">
    <div class="tw-h"><h3>بلدان الشحن</h3><span class="note">في العنوان: <b class="num">{الكود}</b> يُستبدل بكود العميل (مثل SG4417)، و<b>{الاسم}</b> باسمه الأول</span></div>
    <div class="scroll-x"><table class="t prt">
        <thead><tr><th>البلد</th><th>المخزن</th><th>أقل شحنة $</th><th>المدة</th><th style="width:42%">عناوين المخزن ✈️ جوي / 🚢 بحري (كل واحد بسعره · يظهر للعميل كاملاً)</th><th>تخفيض حسب الوزن<div class="note" style="font-weight:400">سطر لكل شريحة: كيلو:سعر<br>مثال 5:10 ثم 20:9</div></th><th>مفعّل</th></tr></thead>
        <tbody>
        @foreach ($origins as $k => $og)
            <tr>
                <td><div style="display:flex;gap:6px"><input name="og[{{ $k }}][flag]" class="inp" value="{{ $og['flag'] ?? '' }}" style="width:54px;text-align:center"><input name="og[{{ $k }}][name]" class="inp" value="{{ $og['name'] }}" style="width:110px"></div></td>
                <td><input name="og[{{ $k }}][city]" class="inp" value="{{ $og['city'] ?? '' }}" style="width:120px"></td>
                <td><input name="og[{{ $k }}][min]" class="inp n2" value="{{ $og['min'] ?? 0 }}"></td>
                <td><input name="og[{{ $k }}][days]" class="inp" value="{{ $og['days'] ?? '' }}" style="width:120px"></td>
                <td><input type="hidden" name="og[{{ $k }}][per_kg]" value="{{ $og['per_kg'] }}">
                    <div class="adl" data-k="{{ $k }}">
                    @foreach (\App\Services\ShippingService::addrs($og, false) as $i => $a)
                        <div class="adr" style="border:1px solid var(--line);border-radius:12px;padding:8px;margin-bottom:8px;background:#fafbfe">
                            <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:6px">
                                <select name="og[{{ $k }}][addrs][{{ $i }}][mode]" class="inp" style="width:110px;height:36px">@foreach (\App\Services\ShippingService::MODES as $m => $ic)<option value="{{ $m }}" @selected(($a['mode'] ?? 'جوي') === $m)>{{ $ic }} {{ $m }}</option>@endforeach</select>
                                <span class="note">سعر الكيلو $</span><input name="og[{{ $k }}][addrs][{{ $i }}][per_kg]" class="inp n2" style="height:36px" value="{{ $a['per_kg'] ?? 0 }}">
                                <label class="ck" style="margin:0"><input type="checkbox" name="og[{{ $k }}][addrs][{{ $i }}][active]" value="1" @checked(!isset($a['active']) || $a['active'])> مفعّل</label>
                                <button type="button" class="btn ghost sm" style="color:var(--down);margin-inline-start:auto" onclick="if(confirm('نمسحو العنوان هذا؟'))this.closest('.adr').remove()">🗑 مسح</button>
                            </div>
                            <textarea name="og[{{ $k }}][addrs][{{ $i }}][address]" class="inp">{{ $a['address'] ?? '' }}</textarea>
                        </div>
                    @endforeach
                    </div>
                    <button type="button" class="btn ghost sm" onclick="sgAddAddr(this)">➕ إضافة عنوان آخر</button></td>
                <td><textarea name="og[{{ $k }}][tiers]" class="inp numr" rows="3" style="width:110px" placeholder="5:10&#10;20:9">{{ $og['tiers'] ?? '' }}</textarea></td>
                <td><input type="checkbox" name="og[{{ $k }}][active]" value="1" @checked(!isset($og['active']) || $og['active'])></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <div class="tw-f"><span class="note">بلد جديد:</span><input name="new_key" class="inp" placeholder="رمز (tr)" style="width:110px;height:38px;direction:ltr"><input name="new_name" class="inp" placeholder="الاسم (تركيا)" style="width:160px;height:38px"></div>
</div>

<div class="card mb" style="padding:18px 22px">
    <h3 style="margin:0 0 4px">💱 سعر صرف الشحن</h3>
    <div class="note" style="margin-bottom:12px">خاص بالشحن فقط. سعر الشحن يُكتب بالدولار، والعميل يشوف سعرين بالدينار: <b>كاش</b> و<b>مصرف</b>. الشحنات المسجلة تحتفظ بسعر الصرف اللي اتسجلت بيه.</div>
    <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-end">
        <div><span class="l" style="font-size:13.5px;font-weight:700;display:block;margin-bottom:6px">💵 دينار كاش (لكل 1$)</span><input name="rate_cash" class="inp n2" style="height:44px;width:160px;font-family:InterNum;font-weight:800;direction:ltr;text-align:right" value="{{ old('rate_cash', (float) \App\Services\AppSettings::get('ship_rate_cash', 0) ?: '') }}" placeholder="{{ number_format($rates['cash'], 3) }}" inputmode="decimal"></div>
        <div><span class="l" style="font-size:13.5px;font-weight:700;display:block;margin-bottom:6px">🏦 دينار مصرف (لكل 1$)</span><input name="rate_bank" class="inp n2" style="height:44px;width:160px;font-family:InterNum;font-weight:800;direction:ltr;text-align:right" value="{{ old('rate_bank', (float) \App\Services\AppSettings::get('ship_rate_bank', 0) ?: '') }}" placeholder="{{ number_format($rates['bank'], 3) }}" inputmode="decimal"></div>
        <div class="note">لو تركت الخانة فارغة يُستخدم آخر سعر صرف مسجل في المنظومة ({{ number_format($rate, 3) }}).</div>
    </div>
</div>

<div class="grid g2 mb">
<div class="card tw">
    <div class="tw-h"><h3>أسعار خاصة لعملاء</h3><span class="note">الحقل الفارغ = السعر العادي</span></div>
    <div class="scroll-x"><table class="t prt">
        <thead><tr><th>العميل</th>@foreach ($origins as $og)<th>{{ $og['flag'] }} {{ $og['name'] }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($special as $c)
            @php $sp = json_decode((string) $c->ship_prices, true) ?: []; @endphp
            <tr><td><b>{{ $c->name }}</b><div class="note num">{{ $c->code }}</div></td>@foreach ($origins as $k => $og)<td><input name="cp[{{ $c->id }}][{{ $k }}]" class="inp n2" value="{{ $sp[$k] ?? '' }}" placeholder="{{ $og['per_kg'] }}"></td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($origins) + 1 }}"><div class="note" style="padding:8px">لا توجد أسعار خاصة بعد</div></td></tr>
        @endforelse
        </tbody>
    </table></div>
    <div class="tw-f"><select name="new_customer" class="inp" style="height:38px;max-width:280px"><option value="">+ إضافة عميل بسعر خاص</option>@foreach ($customers as $c)<option value="{{ $c->id }}">{{ $c->name }} · {{ $c->code }}</option>@endforeach</select></div>
</div>
<div class="card tw">
    <div class="tw-h"><h3>المواقع التي نشتري منها</h3><span class="note">سعر الدولار لكل موقع يظهر للعملاء</span></div>
    <div class="scroll-x"><table class="t prt">
        <thead><tr><th>الموقع</th><th>سعر الدولار (د.ل)</th><th>ملاحظة للعميل</th></tr></thead>
        <tbody>
        @foreach ($platforms as $p)
            <tr><td><b>{{ $p->name }}</b></td><td><input name="pf[{{ $p->id }}][rate]" class="inp n2" value="{{ $p->rate }}" placeholder="{{ number_format($rate, 2) }}"></td><td><input name="pf[{{ $p->id }}][note]" class="inp" value="{{ $notes[$p->id] ?? '' }}" placeholder="مثال: ملابس وأحذية · 12 – 18 يوماً"></td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>
</div>
<button class="btn lg">حفظ الأسعار</button>
</form>
<script>
// ➕ عنوان آخر للبلد (جوي ولا بحري) بسعره
window.sgAddAddr = function (b) {
  const box = b.previousElementSibling, k = box.dataset.k, i = 'n' + Date.now() % 1000000;
  const d = document.createElement('div'); d.className = 'adr';
  d.style.cssText = 'border:1px solid var(--line);border-radius:12px;padding:8px;margin-bottom:8px;background:#fafbfe';
  d.innerHTML = '<div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap;margin-bottom:6px">'
    + '<select name="og[' + k + '][addrs][' + i + '][mode]" class="inp" style="width:110px;height:36px"><option value="جوي">✈️ جوي</option><option value="بحري" selected>🚢 بحري</option></select>'
    + '<span class="note">سعر الكيلو $</span><input name="og[' + k + '][addrs][' + i + '][per_kg]" class="inp n2" style="height:36px" value="0">'
    + '<label class="ck" style="margin:0"><input type="checkbox" name="og[' + k + '][addrs][' + i + '][active]" value="1" checked> مفعّل</label>'
    + '<button type="button" class="btn ghost sm" style="color:var(--down);margin-inline-start:auto" onclick="this.closest(\'.adr\').remove()">🗑 مسح</button></div>'
    + '<textarea name="og[' + k + '][addrs][' + i + '][address]" class="inp" placeholder="{الكود} {الاسم}&#10;العنوان…"></textarea>';
  box.appendChild(d); d.querySelector('textarea').focus();
};
</script>
@php $card = (array) \App\Services\AppSettings::get('ship_card', []); @endphp
<div class="card mt" id="card">
    <h3>🖼 صورة الكود <span class="h-note">صورة ثابتة، وينكتب عليها كود كل عميل (SG1234) وتنبعت مع العناوين</span></h3>
    <form method="post" action="{{ route('sg.shipping.card') }}" enctype="multipart/form-data">
        @csrf
        <div class="grid g2 f"><div><label class="l">الصورة (JPG / PNG)</label><input type="file" name="bg" class="inp" accept="image/png,image/jpeg">@if (!empty($card['bg']))<small class="note">✓ فيه صورة مرفوعة — ارفع جديدة لو تبي تبدّلها</small>@endif</div>
            <div><label class="l">لون الكود</label><input type="color" name="color" class="inp" value="{{ $card['color'] ?? '#ffffff' }}" style="height:44px"></div></div>
        <div class="grid g2 f">
            <div><label class="l">مكان الكود أفقياً % <span class="note">(50 = النص)</span></label><input name="x" class="inp numr" value="{{ $card['x'] ?? 50 }}"></div>
            <div><label class="l">مكان الكود عمودياً % <span class="note">(0 فوق · 100 تحت)</span></label><input name="y" class="inp numr" value="{{ $card['y'] ?? 80 }}"></div>
        </div>
        <div class="f"><label class="l">حجم الكود % من عرض الصورة</label><input name="size" class="inp numr" value="{{ $card['size'] ?? 6 }}"></div>
        <button class="btn">حفظ</button>
    </form>
    @if (!empty($card['bg']))<div class="mt"><div class="note mb">معاينة (كود تجريبي):</div><img src="{{ route('sg.shipping.card.preview', ['code' => '1234']) }}&t={{ time() }}" style="max-width:100%;max-height:360px;border-radius:12px;border:1px solid var(--line)"></div>@endif
</div>
@endsection
