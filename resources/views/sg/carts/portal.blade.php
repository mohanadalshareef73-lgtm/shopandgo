@extends('sg.layout')
@section('title', 'تأكيد الحجوزات')
@php use App\Services\SheinQuotationPdf; $pdf = app(SheinQuotationPdf::class); $pay = request('pay'); $bph = (array) \App\Services\AppSettings::get('booking_phones', ['0920703777', '0920403444']);
    $bNumsAll = array_values(array_unique(array_merge((array) \App\Services\AppSettings::get('booking_numbers', []), array_filter($bph, fn ($x) => !str_contains((string) $x, '@')))));
    $bLast = \App\Services\AppSettings::get('booking_last_phone', $bNumsAll[0] ?? '');
    // 📢 قروب الحجز (منتظرة): يطلع أول خيار في القائمة
    $bGroup = collect((array) \App\Services\AppSettings::get('wa_groups_recent', []))->filter(fn ($g) => !empty($g['tag']) && !empty($g['id']))->first(); @endphp
@section('content')
<div class="top">
    <div><h1>تأكيد الحجوزات</h1><div class="sub">سلات العملاء من حسابهم: اللي دفعوا عربون بتحويل، واللي يبوا يدفعوا كاش عند المندوب</div></div>
    <div class="acts"><button class="sg-burger">☰</button></div>
</div>
<a class="btn mb" style="background:#e8590c;color:#fff;font-size:15px" href="{{ route('sg.bqueue') }}">📋 افتح قائمة الحجز</a>
<datalist id="bNums">@foreach (array_filter($bph, fn ($x) => !str_contains($x, '@')) as $bn)<option value="{{ $bn }}">@endforeach</datalist>
<nav class="tabs">
    @foreach (['ordered' => 'تستنى تأكيد', 'draft' => 'حسبها', 'confirmed' => 'متأكدة', 'rejected' => 'مرفوضة', 'all' => 'الكل'] as $k => $v)
        <a href="?tab={{ $k }}" class="{{ $tab === $k ? 'on' : '' }}">{{ $v }} @if ($k !== 'all')<span class="b {{ $k === 'ordered' && ($counts[$k] ?? 0) ? 'r' : '' }}">{{ $counts[$k] ?? 0 }}</span>@endif</a>
    @endforeach
</nav>
<form method="get" style="display:flex;gap:8px;margin:12px 0;flex-wrap:wrap">@if ($tab === 'draft' || request('tab') === 'draft')<input type="hidden" name="tab" value="draft">@endif <input name="q" id="pcQ" value="{{ request('q') }}" autocomplete="off" oninput="clearTimeout(window.pcT);window.pcT=setTimeout(()=>{sessionStorage.setItem('pcQf','1');this.form.submit()},600)" class="inp" placeholder="🔍 العميل جا للمكتب؟ اكتب كوده أو اسمه أو رقمه أو رقم السلة" style="flex:1;min-width:260px;height:46px;font-size:15px"><button class="btn">بحث</button>@if (request('q'))<a class="btn ghost" href="{{ route('sg.pcarts') }}">✕</a>@endif</form>
@if ($tab === 'search')<div class="hint mb">نتائج البحث: كل سلات العميل اللي انحسبت (حتى اللي ما أكدهاش). افتح <b>📄 PDF التكرار</b> تشوف القطع المكررة ملوّنة، واضغط <b>🏪 حجز في المكتب</b>.</div>@endif
<div style="display:flex;gap:6px;margin:10px 0">
    @foreach (['' => 'كل طرق الدفع', 'transfer' => '🏦 تحويل (عربون)', 'cash_courier' => '🚚 كاش عند المندوب'] as $k => $v)
        <a class="pill {{ (string) $pay === $k ? 'on' : '' }}" href="{{ request()->fullUrlWithQuery(['pay' => $k ?: null, 'page' => null]) }}">{{ $v }}</a>
    @endforeach
</div>
<div class="card tw"><div class="scroll-x">
<table class="t"><thead><tr><th>السلة</th><th>العميل</th><th class="n">القطع</th><th class="n">المبلغ</th><th>الدفع</th><th>للحجز</th><th>القرار</th></tr></thead><tbody>
@forelse ($rows as $ct)
    @php
        try { $q = $ct->retailer === 'amazon' ? (array) $ct->amazon_pricing : $pdf->quotationForPdf($ct); } catch (\Throwable $e) { $q = []; }
        $tot = data_get($q, 'total.amount'); $qty = $ct->retailer === 'amazon' ? data_get($q, 'quantity') : ($q['available_quantity'] ?? null);
        // فيه تكرار؟ (قطعة كميتها أكثر من 1) — أزرار الـ PDF تطلع بس لو فيه
        $rep = $ct->retailer === 'amazon' ? (int) data_get($q, 'quantity', 1) > 1 : collect((array) ($q['items'] ?? []))->contains(fn ($i) => (int) ($i['qty'] ?? 0) > 1 && ($i['availability'] ?? 'available') === 'available');
    @endphp
    <tr>
        <td><b>{{ $ct->retailer_label }} #{{ $ct->id }}</b><small class="num" style="display:block;color:#000;font-size:11.5px">🕒 {{ ($ct->ordered_at ?: $ct->created_at) ? \Carbon\Carbon::parse($ct->ordered_at ?: $ct->created_at)->format('Y-m-d g:i a') : '' }}</small></td>
        <td class="cell2">@if (!$ct->customer_id && !$ct->guest_phone && !in_array($ct->portal_status, ['ordered', 'confirmed'], true))
            @php $waP = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) $ct->whatsapp_customer_phone)); $waP = strlen($waP) > 13 || str_contains((string) $ct->whatsapp_customer_phone, '@') ? '' : $waP; if ($waP === '' && $ct->incomingMessage) { $waP = preg_replace('/^218/', '0', preg_replace('/\D/', '', (string) ($ct->incomingMessage->contact_phone ?: $ct->incomingMessage->phone))); $waP = strlen($waP) > 13 ? '' : $waP; } $waN = optional($ct->incomingMessage)->name; @endphp
            <b>{{ $waN && $waN !== 'زائر' ? $waN : 'عميل' }}</b> <span class="chip n" style="font-size:11px">📱 من الواتساب</span><small class="num">{{ $waP ?: 'رقمه مخفي في الواتساب' }}</small>
        @elseif (!$ct->customer_id)@php $gd = \App\Models\Delivery::where('cart_id', $ct->id)->latest('id')->first(); @endphp<b>{{ $ct->guest_name ?: (optional($gd)->recipient_name ?: 'عميل') }}</b> <span class="chip w" style="font-size:11px">👤 بدون حساب — ينفتحله حساب وقت التأكيد</span><small class="num">{{ $ct->guest_phone ?: optional($gd)->recipient_phone }}</small>@if ($ct->guest_address)<small>📍 {{ $ct->guest_address }}</small>@endif @else<b>{{ optional($ct->customer)->name }}</b><small class="num">@if (!empty(optional($ct->customer)->trusted))✅ موثوق · @endif كود {{ optional($ct->customer)->code }} · {{ optional($ct->customer)->phone_1 }}</small>@endif</td>
        <td class="n num">{{ $qty ?? '—' }}</td>
        <td class="n"><b class="num">{{ $tot ?? '—' }}</b> د.ل</td>
        <td>@if ($ct->pay_method === 'office')<span class="chip n">🏪 في المكتب</span> @if (!empty($ct->paid_amount))<b class="num">{{ \App\Services\TreasuryService::fmt($ct->paid_amount) }}</b>@endif @if (!empty($ct->order_id))<a class="code" href="{{ route('sg.orders.show', $ct->order_id) }}">🧾</a>@endif
            @elseif (!$ct->pay_method)<span class="note">لسه ما اختارش</span>
            @elseif ($ct->pay_method === 'cash_courier')<span class="chip w">🚚 كاش مندوب</span>@if (!empty($ct->paid_amount))<br><span class="chip u">✓ قبض {{ \App\Services\TreasuryService::fmt($ct->paid_amount) }} د.ل</span><div class="note num">{{ $ct->paid_at ? \Carbon\Carbon::parse($ct->paid_at)->diffForHumans() : '' }}</div>@if (!empty($ct->order_id))<a class="code" href="{{ route('sg.orders.show', $ct->order_id) }}">🧾 الإيصال</a>@endif @else<br><span class="note">المندوب لم يستلم بعد{{ !empty($ct->courier_pay) ? ' · يدفع ' . \App\Services\TreasuryService::fmt($ct->courier_pay) : '' }}</span>@endif
            @else<span class="chip u">🏦 تحويل</span>@if (!empty($ct->courier_pay)) <b class="num">{{ \App\Services\TreasuryService::fmt($ct->courier_pay) }}</b>@endif @if (!empty($ct->order_id))<a class="code" href="{{ route('sg.orders.show', $ct->order_id) }}">🧾</a>@endif<br>@if ($ct->receipt_path)
                @php $isPdf = str_ends_with(strtolower($ct->receipt_path), '.pdf'); $dupN = (!empty($ct->receipt_hash) || !empty($ct->receipt_ref)) ? \App\Models\CartQuote::where('id', '!=', $ct->id)->where(fn ($w) => $w->when(!empty($ct->receipt_hash), fn ($x) => $x->orWhere('receipt_hash', $ct->receipt_hash))->when(!empty($ct->receipt_ref), fn ($x) => $x->orWhere('receipt_ref', $ct->receipt_ref)))->count() : 0; @endphp
                @if ($dupN)<span class="chip d" title="نفس الصورة مستعملة في سلات ثانية">🚨 إيصال مكرر ({{ $dupN }}) — راجع</span><br>@endif
                <details><summary class="btn ghost sm" style="display:inline-flex;cursor:pointer">👁 إظهار الإيصال</summary>
                    <div style="margin-top:6px">@if ($isPdf)<iframe src="{{ route('sg.pcarts.receipt', $ct) }}" style="width:260px;height:340px;border:1px solid var(--line);border-radius:8px"></iframe>@else<a href="{{ route('sg.pcarts.receipt', $ct) }}" target="_blank"><img src="{{ route('sg.pcarts.receipt', $ct) }}" style="max-width:260px;max-height:360px;border-radius:8px;border:1px solid var(--line)" loading="lazy"></a>@endif
                    <div class="note">اضغط على الصورة تكبر · لو مطابقة اضغط «تأكيد الحجز»</div></div>
                </details>@endif @endif</td>
        <td><div style="display:flex;gap:6px;flex-wrap:wrap"><form method="post" action="{{ route('sg.pcarts.note', $ct) }}" style="margin:0" onsubmit="const t=prompt('📝 الملاحظة اللي تبي تبعتها للعميل على الواتساب:');if(!t||!t.trim())return false;this.text.value=t;return true">@csrf<input type="hidden" name="text"><button class="btn ghost sm">📝 ملاحظة</button></form>
            <button type="button" class="btn ghost sm" onclick="navigator.clipboard.writeText({{ json_encode($ct->source_url) }});this.textContent='✓ تنسخ'">⧉ نسخ الرابط</button>
            @if ($rep)<a class="btn ghost sm" href="{{ route('sg.pcarts.download', $ct) }}">⬇ تحميل PDF</a>
            <a class="btn ghost sm" style="color:#e8590c" href="{{ route('sg.pcarts.booking', $ct) }}" target="_blank">📄 PDF التكرار 🔁</a>@endif
        </div></td>
        <td>@canany(['cart-edit', 'cart-list'])
            {{-- 1) التأكيد: ينعمل الإيصال ويوصل العميل التأكيد ورابط الإيصال --}}
            @if ($ct->portal_status === 'ordered' && ($ct->pay_method === 'cash_courier' || !$ct->customer_id))
            <form method="post" action="{{ route('sg.pcarts.delivery', $ct) }}" style="margin-bottom:6px" onsubmit="return confirm('نبعتو الحجز لطلبيات دليفري؟ المندوب يمشي يقبض العربون')">@csrf<button class="btn" style="background:#e8590c;color:#fff">🛵 ارسال دليفري</button></form>
            @endif
            @if ($ct->portal_status === 'ordered')
            <form method="post" action="{{ route('sg.pcarts.decide', $ct) }}" style="margin-bottom:6px" onsubmit="return confirm({{ json_encode($ct->customer_id ? 'نأكدو الحجز؟ ينعمل الإيصال ويوصل العميل التأكيد' : 'نأكدو الحجز؟ ينفتحله حساب (لو ما عندهش) وينعمل الإيصال ويوصله على الواتساب') }})">@csrf
                <input type="hidden" name="send_whatsapp" value="1">
                <button name="do" value="confirm" class="btn green" style="font-size:15px;padding:9px 16px">✓ تأكيد الحجز</button>
            </form>
            <form method="post" action="{{ route('sg.pcarts.decide', $ct) }}" style="margin:0 0 6px" onsubmit="return confirm('نرفضو الحجز؟ (بدون رسالة للعميل)')">@csrf<input type="hidden" name="send_whatsapp" value="0"><button name="do" value="reject" class="btn ghost sm" style="color:var(--down)">✕ رفض</button></form>
            <form method="post" action="{{ route('sg.pcarts.decide', $ct) }}" style="margin:0 0 6px" onsubmit="return confirm('نحذفو الحجز #{{ $ct->id }} نهائي؟ يختفي من كل القوائم وما توصلش رسالة للعميل')">@csrf<input type="hidden" name="del" value="1"><button name="do" value="ignore" class="btn ghost sm" style="color:#c62828">🗑 حذف</button></form>
            @endif
            @if (empty($ct->order_id) && $ct->customer_id && in_array($ct->portal_status, ['draft', 'ordered', null], true))
            <details style="margin-bottom:6px"><summary class="btn ghost sm" style="display:inline-flex;cursor:pointer">🏪 حجز في المكتب</summary>
                <form method="post" action="{{ route('sg.pcarts.office', $ct) }}" style="margin-top:6px;display:flex;flex-direction:column;gap:6px;min-width:220px">@csrf
                    <input name="paid" class="inp numr" placeholder="كم دفع؟ (قيمة الطلب {{ $tot ?? '—' }})" value="{{ $tot }}" required>
                    <select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id === \App\Services\AppSettings::cashPaymentId())>{{ $pm->name }}</option>@endforeach</select>
                    <button class="btn green sm" onclick="return confirm('نحجزوها؟ ينعمل إيصال ويوصل العميل')">✓ احجز واعمل إيصال</button>
                    <span class="note">ينعمل إيصال في الطلبات، والمبلغ يدخل الخزينة اللي اخترتها، ويوصل العميل الإيصال</span>
                </form>
            </details>
            @endif
            {{-- 🧮 حسبها وما أكدهاش: ⚡ ابعتها لرقم (من غير تأكيد) · ✓ أكدها · ✕ تجاهل --}}
            @if (in_array($tab, ['draft', 'search'], true) && in_array($ct->portal_status, [null, 'draft'], true))
            <form method="post" action="{{ route('sg.pcarts.booknow', $ct) }}" style="margin:0 0 6px" onsubmit="const v=(this.to_new.value||this.to_phone.value).trim();if(!v){alert('اختار الرقم');return false}return confirm('نبعتو السلة (الرابط + PDF) للرقم '+v+'؟')">@csrf
                <input type="hidden" name="wa" value="1">
                @if (!empty($ct->book_sent_to))<div style="background:#e9f7ef;color:#1e7b45;border-radius:10px;padding:4px 8px;margin-bottom:4px;font-size:12.5px;font-weight:800">✅ انبعتت للرقم <span class="num">{{ $ct->book_sent_to }}</span></div>@endif
                <div style="display:flex;gap:4px;margin-bottom:4px;flex-wrap:wrap">
                    <select name="to_phone" class="inp numr" style="height:36px;width:150px;font-size:14px" onchange="this.form.to_new.style.display=this.value?'none':'';if(!this.value)this.form.to_new.focus()">@if ($bGroup)<option value="{{ $bGroup['id'] }}" @selected($bLast === $bGroup['id'])>📢 قروب الحجز ({{ $bGroup['name'] ?: 'منتظرة' }})</option>@endif @foreach ($bNumsAll as $bn)<option value="{{ $bn }}" @selected($bn === $bLast)>{{ $bn }}</option>@endforeach<option value="">✏️ نكتب رقم…</option></select>
                    <button class="btn" style="background:#e8590c;color:#fff;padding:7px 12px">⚡ ابعتها</button>
                </div>
                <input name="to_new" class="inp numr" placeholder="اكتب الرقم" style="height:34px;width:200px;font-size:13px;{{ $bNumsAll ? 'display:none' : '' }}" inputmode="tel">
            </form>
            <form method="post" action="{{ route('sg.pcarts.decide', $ct) }}" style="display:flex;gap:4px;flex-wrap:wrap" onsubmit="return this.submitted==='ignore' || confirm('نأكدو الحجز؟ ينعمل الإيصال ويوصل العميل (ولو ما عندهش حساب ينفتحله برقم الواتساب)')">@csrf
                <input type="hidden" name="send_whatsapp" value="1">
                @if (!$ct->customer_id)<details style="width:100%;border:1px solid var(--line);border-radius:10px;padding:6px 10px;margin-bottom:4px"><summary style="cursor:pointer;font-weight:700;font-size:13.5px">👤 افتحله حساب عادي <span class="note">(اختياري)</span></summary>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:6px;margin-top:6px">
                        <input name="acc_name" class="inp" placeholder="الاسم" value="{{ $ct->guest_name ?? '' }}" style="height:36px">
                        <input name="acc_phone" class="inp numr" placeholder="رقم الهاتف" value="{{ $ct->guest_phone ?? '' }}" inputmode="tel" style="height:36px">
                        <input name="acc_city" class="inp" placeholder="المدينة" list="lyCities" value="بنغازي" style="height:36px">
                        <input name="acc_area" class="inp" placeholder="المنطقة" style="height:36px">
                        <input name="acc_code" class="inp numr" placeholder="كود العميل (اختياري)" style="height:36px;grid-column:1/-1">
                    </div></details>@endif
                <button name="do" value="confirm" class="btn green sm" onclick="this.form.submitted='confirm'">✓ تأكيد الحجز</button>
                <button name="do" value="ignore" class="btn ghost sm" onclick="this.form.submitted='ignore'" title="تختفي من القائمة بس">✕ تجاهل</button>
            </form>
            @endif
            {{-- 2) بعد التأكيد: ⚡ احجز يبعت الكود + الرابط + الـ PDF للرقم اللي تختاره --}}
            @if ($ct->portal_status === 'confirmed')
            <span class="chip u" style="margin-bottom:6px">✓ متأكد @if (!empty($ct->order_id))· <a class="code" href="{{ route('sg.orders.show', $ct->order_id) }}">🧾</a>@endif</span>
            @if (!empty($ct->book_sent_to))<div style="background:#e9f7ef;color:#1e7b45;border-radius:10px;padding:6px 10px;margin:6px 0;font-size:13.5px;font-weight:800">✅ انبعت للرقم <span class="num">{{ $ct->book_sent_to }}</span><small style="display:block;font-weight:600">{{ \Carbon\Carbon::parse($ct->book_sent_at)->format('m-d g:i a') }}</small></div>@endif
            <form method="post" action="{{ route('sg.pcarts.booknow', $ct) }}" style="margin:6px 0" onsubmit="const v=(this.to_new.value||this.to_phone.value).trim();if(!v){alert('اختار الرقم');return false}return confirm('نبعتو الحجز (الكود + الرابط + PDF) للرقم '+v+'؟')">@csrf
                <div style="display:flex;gap:4px;margin-bottom:4px;flex-wrap:wrap">
                    <select name="to_phone" class="inp numr" style="height:36px;width:150px;font-size:14px" onchange="this.form.to_new.style.display=this.value?'none':'';if(!this.value)this.form.to_new.focus()">@if ($bGroup)<option value="{{ $bGroup['id'] }}" @selected($bLast === $bGroup['id'])>📢 قروب الحجز ({{ $bGroup['name'] ?: 'منتظرة' }})</option>@endif @foreach ($bNumsAll as $bn)<option value="{{ $bn }}" @selected($bn === $bLast)>{{ $bn }}</option>@endforeach<option value="">➕ رقم جديد…</option></select>
                    <button class="btn" style="background:#e8590c;color:#fff;font-size:15px;padding:8px 14px">⚡ احجز{{ !empty($ct->book_sent_to) ? ' مرة ثانية' : '' }}</button>
                </div>
                <input name="to_new" class="inp numr" placeholder="رقم جديد (يتحفظ في القائمة)" style="height:34px;width:200px;font-size:13px;{{ $bNumsAll ? 'display:none' : '' }}" inputmode="tel">
            </form>
            @elseif ($ct->portal_status === 'rejected')<span class="chip d">مرفوض</span>@endif @endcanany</td>
    </tr>
@empty
    <tr><td colspan="7"><div class="empty"><span class="ic">🛒</span>ما فيش حجوزات هنا</div></td></tr>
@endforelse
</tbody></table></div>
<div class="tw-f">@include('sg.partials.pager', ['p' => $rows])</div></div>
<script>
// 🔍 كل ما تكتب يفلتر: نرجعو المؤشر لآخر الخانة بعد ما تتحدث الصفحة
(function () { try { if (sessionStorage.getItem('pcQf')) { sessionStorage.removeItem('pcQf'); const q = document.getElementById('pcQ'); if (q) { q.focus(); const v = q.value; q.value = ''; q.value = v; } } } catch (e) {} })();
</script>
@include('sg.partials.cities')
@endsection
