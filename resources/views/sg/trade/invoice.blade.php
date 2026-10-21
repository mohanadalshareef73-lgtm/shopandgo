@extends('sg.layout')
@section('title', 'فاتورة ' . $inv->code)
@php use App\Services\TreasuryService as TS; use App\Models\Trade\Move; $f = fn ($n) => TS::fmt($n); $base = $inv->paidByPartners(); $wts = \App\Services\TradeService::weights($inv); $due = max(0, (float) $inv->sale_lyd - (float) $inv->collected); $est = (float) ($inv->status === 'closed' ? $inv->profit : ((float) $inv->sale_lyd - $inv->totalCost())); @endphp
@push('style')
<style>
.tk { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; margin-bottom: 14px; }
.tk .card { padding: 12px; } .tk .l { font-size: 12.5px; color: var(--ink-3); } .tk .v { font-size: 20px; font-weight: 800; }
.pl { color: #0f8a5f; font-weight: 800; } .mn { color: #c62828; font-weight: 800; }
.fm { display: flex; gap: 8px; flex-wrap: wrap; align-items: end; } .fm .inp { height: 38px; width: auto; }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>🧾 {{ $inv->code }} @if ($inv->status === 'closed')<span class="chip u">مقفولة</span>@else<span class="chip w">مفتوحة</span>@endif</h1>
        <div class="sub">{{ $inv->kind }}{{ $inv->source ? ' · ' . $inv->source : '' }} · {{ $inv->created_at->format('Y-m-d') }}{{ $inv->customer ? ' · العميل ' . $inv->customer->name . ' (' . $inv->customer->code . ')' : (!empty($inv->customer_name) ? ' · العميل ' . $inv->customer_name : '') }}</div></div>
    <div class="acts">@if ($inv->status !== 'closed')<button class="btn" data-modal="mEditInv">✏️ تعديل الفاتورة</button><form method="post" action="{{ route('sg.trade.invoice.delete', $inv) }}" style="display:inline" data-confirm="نحذفو الفاتورة {{ $inv->code }} نهائي؟ كل حركاتها ترجع (الخزينة وأرصدة الشركاء وخزينة الشركة) وطلبية العميل تتلغى">@csrf<button class="btn ghost" style="color:#c62828">🗑 حذف</button></form>@endif<a class="btn ghost" href="{{ route('sg.trade', ['tab' => 'invoices']) }}">↩ الفواتير</a><button class="sg-burger">☰</button></div>
</div>
<div class="tk">
    <div class="card"><div class="l">💲 بالدولار</div><div class="v num">{{ $inv->usd_total ? $f($inv->usd_total) : '—' }}</div><div class="note">سعر الصرف {{ $inv->rate ?: '—' }}</div></div>
    <div class="card"><div class="l">🧾 تكلفة الفاتورة</div><div class="v num">{{ $f($inv->cost_lyd) }}</div><div class="note">+ إضافية {{ $f($inv->extra_lyd) }}</div></div>
    <div class="card"><div class="l">= التكلفة كاملة</div><div class="v num">{{ $f($inv->totalCost()) }}</div></div>
    <div class="card"><div class="l">🏷 سعر البيع</div><div class="v num">{{ $f($inv->sale_lyd) }}</div></div>
    <div class="card"><div class="l">💵 سدد العميل</div><div class="v num pl">{{ $f($inv->collected) }}</div><div class="note">الباقي {{ $f($due) }}</div></div>
    <div class="card"><div class="l">{{ $inv->status === 'closed' ? '📈 الربح' : '📈 الربح المتوقع' }}</div><div class="v num {{ $est < 0 ? 'mn' : 'pl' }}">{{ $f($est) }}</div></div>
</div>

@if (!empty($inv->items))
<div class="card tw mb">
    <div class="tw-h"><h3>🛍 المنتجات <span class="h-note">{{ count($inv->items) }} صنف</span></h3></div>
    <table class="t"><thead><tr><th>المنتج</th><th class="n">العدد</th><th class="n">السعر</th></tr></thead><tbody>
    @foreach ($inv->items as $it)<tr><td>{{ $it['name'] ?? '' }}</td><td class="n num">{{ $it['qty'] ?? 1 }}</td><td class="n num">{{ $f($it['price'] ?? 0) }}</td></tr>@endforeach
    </tbody></table>
</div>
@endif
<div class="card mb" style="padding:12px 14px;display:flex;gap:10px;align-items:center;flex-wrap:wrap">
    <b>👤 العميل:</b>
    @if ($inv->customer)<span>{{ $inv->customer->name }} · كود <b class="num">{{ $inv->customer->code }}</b></span>@else<span class="note">ما فيش</span>@endif
    @if ($inv->order)<a class="chip u" href="{{ route('sg.orders.show', $inv->order) }}">📦 طلبيته في حسابه: {{ $inv->order->code }} · باقي {{ $f(max(0, $inv->order->remaining())) }}</a>
    @elseif ($inv->customer && $inv->status !== 'closed')<form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}">@csrf<input type="hidden" name="a" value="order"><button class="btn sm">📦 اعمل طلبية في حسابه</button></form>@endif
    @if (!$inv->order && $inv->status !== 'closed')<form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}" class="fm" style="margin-inline-start:auto">@csrf<input type="hidden" name="a" value="customer"><input name="customer_code" class="inp numr" style="width:120px" placeholder="كود العميل" required><button class="btn ghost sm">ربط</button></form>@endif
</div>
@if ($inv->order)<div class="note mb">💡 العميل يقدر يسدد عادي من «التسليم» ولا الإيصال — الفلوس تدخل خزينة التجارة أوتوماتيك وتتحسب هني.</div>@endif

@if ($tc && $inv->status !== 'closed')
@php $dueL = max(0, (float) $inv->sale_lyd - (float) $inv->collected); $rt = (float) $inv->rate ?: 0; @endphp
<form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}" class="card mb" style="padding:14px" id="chF">@csrf<input type="hidden" name="a" value="charge"><input type="hidden" name="mode" id="chM">
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:10px"><b>💳 من حساب العميل · {{ $tc->name }}</b>
        <span class="chip {{ $tc->usd < 0 ? 'd' : 'u' }} num">رصيده $ {{ $f($tc->usd) }}</span><span class="chip {{ $tc->lyd < 0 ? 'd' : 'u' }} num">رصيده د.ل {{ $f($tc->lyd) }}</span>
        <a class="note" href="{{ route('sg.trade', ['tab' => 'customers', 'c' => $tc->id]) }}">📜 حسابه</a></div>
    <div class="fm">
        <div><label class="l">المبلغ د.ل</label><input name="amount" id="chA" class="inp numr" style="width:130px" value="{{ $dueL + 0 }}" oninput="chC()"></div>
        <div><label class="l">سعر الصرف</label><input name="rate" id="chR" class="inp numr" style="width:100px" value="{{ $rt + 0 }}" oninput="chC()"></div>
        <div><label class="l">= بالدولار</label><div class="inp numr" id="chU" style="width:120px;font-weight:800;display:flex;align-items:center">—</div></div>
        <button type="button" class="btn" style="background:#1c45a3;color:#fff" onclick="chGo('usd')">💲 اخصم من رصيده الدولار</button>
        <button type="button" class="btn ghost" onclick="chGo('lyd')">اخصم من رصيده الدينار</button>
        <button type="button" class="btn ghost" style="color:#c62828" onclick="chGo('debt')">🧾 خليها عليه دين</button>
    </div>
</form>
<script>function chC(){const a=parseFloat(document.getElementById('chA').value)||0,r=parseFloat(document.getElementById('chR').value)||0;document.getElementById('chU').textContent=r?(Math.round(a/r*100)/100).toLocaleString('en-US')+' $':'—'}chC();
function chGo(m){const a=document.getElementById('chA').value,u=document.getElementById('chU').textContent;if(!(parseFloat(a)>0))return alert('اكتب المبلغ');
 if(!confirm(m==='usd'?'نخصمو '+u+' من رصيده الدولار ('+a+' د.ل)؟':(m==='debt'?'نخليو '+a+' د.ل دين عليه؟':'نخصمو '+a+' د.ل من رصيده الدينار؟')))return;document.getElementById('chM').value=m;document.getElementById('chF').submit()}</script>
@endif

@if ($inv->status !== 'closed')
<div class="card mb" style="padding:14px">
    <form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}" class="fm">@csrf<input type="hidden" name="a" value="pay">
        <b>💵 سداد من العميل</b>
        <input name="amount" class="inp numr" style="width:120px" placeholder="المبلغ" required>
        <select name="wallet_id" class="inp">@foreach ($wallets as $w)<option value="{{ $w->id }}">{{ $w->name }}</option>@endforeach</select>
        <input name="note" class="inp" style="width:160px" placeholder="ملاحظة"><button class="btn green">تسجيل</button>
    </form>
    <form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}" class="fm" style="margin-top:10px">@csrf<input type="hidden" name="a" value="sale">
        <b>🏷 سعر البيع</b><input name="amount" class="inp numr" style="width:120px" value="{{ (float) $inv->sale_lyd + 0 }}"><button class="btn ghost sm">تعديل</button>
    </form>
    <form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}" style="margin-top:12px" data-confirm="نقفلو الفاتورة؟ الربح ({{ $f((float) $inv->collected - $inv->totalCost()) }} د.ل = اللي سدده العميل − التكلفة) يتقسم على الشركاء على قد اللي دفعه كل واحد">@csrf<input type="hidden" name="a" value="close"><button class="btn" style="background:#262c4a;color:#fff">🔒 إقفال الفاتورة وتقسيم الربح</button></form>
</div>
@endif

<form method="post" action="{{ route('sg.trade.invoice.act', $inv) }}" class="card tw mb">@csrf<input type="hidden" name="a" value="pct">
    <div class="tw-h"><h3>👥 الشركاء في الفاتورة</h3>@if ($inv->status !== 'closed')<button class="btn ghost sm" style="margin-inline-start:auto">💾 حفظ النسب</button>@endif</div>
    <table class="t"><thead><tr><th>الشريك</th><th>من وين</th><th class="n">دفع</th><th class="n">نسبة الربح %</th><th class="n">{{ $inv->status === 'closed' ? 'نصيبه من الربح' : 'نصيبه المتوقع' }}</th><th class="n">يرجعله كامل</th></tr></thead><tbody>
    @foreach ($inv->shares as $s)
        @php $pct = $wts[$s->id] ?? 0; $pr = $inv->status === 'closed' ? (float) $s->profit : round($est * $pct, 2); @endphp
        <tr><td><b>{{ optional($s->partner)->name }}</b></td><td class="note">{{ $s->source }}</td><td class="n num">{{ $f($s->paid) }}</td>
            <td class="n num">@if ($inv->status !== 'closed')<input name="pct[{{ $s->id }}]" class="inp numr" style="width:80px;height:32px" value="{{ $s->pct !== null ? $s->pct + 0 : '' }}" placeholder="{{ round($pct * 100, 1) }}">@else{{ round($pct * 100, 1) }}%@endif</td>
            <td class="n num {{ $pr < 0 ? 'mn' : 'pl' }}">{{ $f($pr) }}</td><td class="n num"><b>{{ $f((float) $s->paid + $pr) }}</b></td></tr>
    @endforeach
    </tbody></table>
    <div class="note" style="padding:8px 14px">💡 النسبة اللي تكتبها هي اللي تمشي · اللي فاضيين ياخذوا الباقي على قد اللي دفعوه · تقدر تحط نسبة لشريك ما دفعش</div>
</form>

<div class="card tw">
    <div class="tw-h"><h3>📜 حركات الفاتورة</h3></div>
    <table class="t"><thead><tr><th>الوقت</th><th>الحركة</th><th>الشريك</th><th>الخزينة</th><th class="n">الخزينة ±</th><th class="n">الرصيد ±</th><th>ملاحظة</th></tr></thead><tbody>
    @foreach ($moves as $m)
        <tr><td class="num">{{ $m->created_at->format('m-d g:i a') }}</td><td>{{ Move::TYPES[$m->type] ?? $m->type }}</td><td>{{ optional($m->partner)->name ?: '—' }}</td><td>{{ optional($m->wallet)->name ?: '—' }}</td>
            <td class="n num {{ $m->wallet_change < 0 ? 'mn' : ($m->wallet_change > 0 ? 'pl' : '') }}">{{ $m->wallet_id ? $f($m->wallet_change) : '' }}</td>
            <td class="n num {{ $m->partner_change < 0 ? 'mn' : ($m->partner_change > 0 ? 'pl' : '') }}">{{ $m->partner_id ? $f($m->partner_change) : '' }}</td>
            <td class="note">{{ $m->note }}</td></tr>
    @endforeach
    </tbody></table>
</div>
@if ($inv->status !== 'closed')
<div class="modal-bg" id="mEditInv"><div class="modal" style="max-width:780px;width:100%">
    <h3>✏️ تعديل {{ $inv->code }}</h3>
    <form method="post" action="{{ route('sg.trade.invoice.update', $inv) }}">@csrf
        <div class="grid g3">
            <div><label class="l">👤 اسم العميل</label><input name="customer_name" class="inp" value="{{ $inv->customer_name ?? '' }}"></div>
            <div><label class="l">🌐 الموقع</label><input name="source" class="inp" value="{{ $inv->source }}"></div>
            <div><label class="l">📦 الكمية</label><input name="qty" class="inp numr" value="{{ $inv->qty ?? 1 }}"></div>
            <div><label class="l">💲 الدولار</label><input name="usd_total" class="inp numr" value="{{ (float) $inv->usd_total + 0 }}"></div>
            <div><label class="l">🇱🇾 دفعت بالليبي *</label><input name="cost_lyd" class="inp numr" value="{{ (float) $inv->cost_lyd + 0 }}" required></div>
            <div><label class="l">💱 سعر الصرف (فاضي = تلقائي)</label><input name="rate" class="inp numr" value=""></div>
            <div><label class="l">➕ مصاريف إضافية</label><input name="extra_lyd" class="inp numr" value="{{ (float) $inv->extra_lyd + 0 }}"></div>
            <div><label class="l">🏷 سعر البيع</label><input name="sale_lyd" class="inp numr" value="{{ (float) $inv->sale_lyd + 0 }}"></div>
            <div><label class="l">ملاحظات</label><input name="note" class="inp" value="{{ $inv->note }}"></div>
        </div>
        <h4 style="margin:12px 0 6px">🛍 المنتجات <button type="button" class="btn ghost sm" onclick="addItem('itE')">+ منتج</button></h4>
        @php $its = (array) ($inv->items ?? []); @endphp
        <table class="t" style="{{ count($its) ? '' : 'display:none' }}"><thead><tr><th>اسم المنتج</th><th>العدد</th><th>السعر د.ل</th><th></th></tr></thead><tbody id="itE">
            @foreach ($its as $ii => $it)<tr><td><input name="items[{{ $ii }}][name]" class="inp" value="{{ $it['name'] ?? '' }}"></td><td><input name="items[{{ $ii }}][qty]" class="inp numr iq" style="width:70px" value="{{ $it['qty'] ?? 1 }}" oninput="sumQty(this)"></td><td><input name="items[{{ $ii }}][price]" class="inp numr" style="width:110px" value="{{ isset($it['price']) ? $it['price'] + 0 : '' }}"></td><td><button type="button" class="btn ghost sm" style="color:#c62828" onclick="const f=this.closest('form');this.closest('tr').remove();sumQty(f)">✕</button></td></tr>@endforeach
        </tbody></table>
        <h4 style="margin:12px 0 6px">👥 الشركاء <span class="note">— فرق الدفع يتعدل في رصيده · فاضي = يتشال</span></h4>
        <table class="t"><thead><tr><th>الشريك</th><th>دفع د.ل</th><th>نسبة الربح %</th></tr></thead><tbody>
        @foreach ($partners as $k => $p)
            @php $sh = $inv->shares->firstWhere('partner_id', $p->id); @endphp
            <tr><td><input type="hidden" name="s[{{ $k }}][partner_id]" value="{{ $p->id }}"><b>{{ $p->name }}</b><small class="note" style="display:block">رصيده {{ $f($p->balance) }}</small></td>
                <td><input name="s[{{ $k }}][paid]" class="inp numr" style="width:120px" value="{{ $sh ? (float) $sh->paid + 0 : '' }}"></td>
                <td><input name="s[{{ $k }}][pct]" class="inp numr" style="width:90px" value="{{ $sh && $sh->pct !== null ? $sh->pct + 0 : '' }}" placeholder="تلقائي"></td></tr>
        @endforeach
        </tbody></table>
        <div class="note" style="margin-top:6px">⚠ تغيير «دفعت بالليبي» يتعدل في خزينة التجارة بالفرق</div>
        <div style="display:flex;gap:8px;margin-top:12px"><button class="btn green">💾 حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endif
<script>let itN=50;
// 🛍 المنتجات: ما تطلعش لين تضغط «+ منتج» · كمية كل منتج تنجمع في خانة الكمية
function addItem(id){const b=document.getElementById(id);b.closest('table').style.display='';const r=document.createElement('tr');r.innerHTML='<td><input name="items['+itN+'][name]" class="inp" placeholder="اسم المنتج"></td><td><input name="items['+itN+'][qty]" class="inp numr iq" style="width:70px" value="1" oninput="sumQty(this)"></td><td><input name="items['+itN+'][price]" class="inp numr" style="width:110px"></td><td><button type="button" class="btn ghost sm" style="color:#c62828" onclick="const f=this.closest(\'form\');this.closest(\'tr\').remove();sumQty(f)">✕</button></td>';itN++;b.appendChild(r);r.querySelector('input').focus();sumQty(b)}
function sumQty(el){const f=el.closest?el.closest('form'):el;if(!f)return;const q=f.querySelectorAll('.iq');if(!q.length)return;let t=0;q.forEach(i=>t+=parseInt(i.value)||0);const m=f.querySelector('input[name=qty]');if(m)m.value=Math.max(1,t)}
</script>
@endsection
