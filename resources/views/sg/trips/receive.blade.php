@extends('sg.layout')
@section('title', 'استلام ' . $trip->trip_code)
@section('bodyClass', 'soft')
@php
    use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n);
    $done = $orders->where('arrived', true)->count();
    $sorted = $orders->where('arrived', false)->whereNotNull('received_at')->count();
    $pending = $orders->where('arrived', true)->whereNull('arrived_notified_at')->count();
@endphp
@push('style')
<style>
table.rcv { width: 100%; border-collapse: collapse; }
table.rcv th { font-size: 12.5px; color: var(--ink-3); font-weight: 400; text-align: right; padding: 10px 8px; border-bottom: 1px solid var(--line); background: #fafbfc; white-space: nowrap; }
table.rcv td { padding: 8px; border-bottom: 1px solid var(--line-soft); font-size: 14px; white-space: nowrap; vertical-align: middle; }
table.rcv .ci { width: 100%; min-width: 58px; height: 40px; border: 1px solid var(--line); border-radius: 9px; padding: 0 9px; font-family: InterNum; font-weight: 700; font-size: 15px; direction: ltr; text-align: right; background: #fff; }
table.rcv .ci.auto { background: #f3f8fe; border-color: #cfe0f7; color: var(--s1); }
table.rcv .ci.warn { border-color: #f0c674; background: var(--warn-bg); color: var(--warn); }
table.rcv select.ci { font-family: Somar; font-weight: 400; font-size: 13.5px; direction: rtl; }
table.rcv tr.ok td { background: #f6fbf8; }
.dot { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; font-size: 13px; }
.dot.ok { background: var(--up); color: #fff; } .dot.no { border: 2px dashed #c9cdd8; } .dot.mid { background: var(--warn-bg); font-size: 12px; }
.scan { display: flex; align-items: center; gap: 10px; background: var(--brand); color: #fff; border-radius: 14px; padding: 10px 14px; margin-bottom: 14px; }
.scan input { flex: 1; height: 44px; border: 0; border-radius: 10px; padding: 0 12px; font: inherit; font-size: 16px; }
tr.flash td { background: #fff7d1 !important; }
table.rcv .pkx { display: flex; gap: 4px; margin-top: 4px; }
table.rcv .pkx .ci { min-width: 0; }
table.rcv .pkadd { margin-top: 4px; border: 1px dashed var(--line); background: none; border-radius: 8px; padding: 2px 8px; font-size: 12px; cursor: pointer; color: var(--muted, #666); }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.trips.index') }}">الرحلات</a> › <a href="{{ route('finance.trips.show', $trip->id) }}">{{ $trip->trip_code }}</a> › استلام</div>
<div class="top">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap"><h1 class="code">{{ $trip->trip_code }}</h1><span class="chip u" style="font-size:13.5px">● {{ $trip->summary()['status'] }}</span><span class="note">{{ $orders->count() }} إيصال · {{ $orders->pluck('customer_id')->unique()->count() }} عميل</span></div>
    <div class="acts">@if ($pending)<a class="btn" href="{{ route('sg.trips.notify', $trip->id) }}">📨 مراجعة الرسائل <span class="chip w" style="margin-inline-start:4px">{{ $pending }}</span></a>@endif<a class="btn ghost" href="{{ route('order.ready.print') }}" target="_blank">🖨 قائمة الطرود</a><button class="sg-burger">☰</button></div>
</div>

<div class="grid g2 mb">
    <div class="card"><div class="note">جاهزة للاستلام{{ $sorted ? ' · ' . $sorted . ' بياناتها مسجلة' : '' }}</div><div class="big-num"><span class="num" id="doneN">{{ $done }}</span> <span class="note" style="font-size:16px">من {{ $orders->count() }} إيصال</span></div><div class="prog" style="margin:10px 0 6px"><div class="b"><i style="width:{{ $orders->count() ? $done / $orders->count() * 100 : 0 }}%"></i></div></div><div class="note">البضاعة التي لم تصل اترك خانتها فارغة · الوزن اختياري · إذا وصلت أقل، اكتب قيمة النواقص</div></div>
    <div class="card">
        <h3 style="margin-bottom:4px">سعر الشحن على العميل</h3>
        <div class="note">يُحسب تلقائياً من الوزن، ويمكنك تعدله لأي إيصال · <a href="{{ route('sg.settings') }}" style="color:var(--s1)">تغيير</a></div>
        <div style="display:flex;gap:10px;align-items:center;margin-top:10px;font-size:15px"><b class="num" style="font-size:24px">{{ $f($perKg) }}</b> د.ل للكيلو <span class="note">·</span> الحد الأدنى <b class="num">{{ $f($minShip) }}</b></div>
    </div>
</div>

<div class="scan">🔎 <input id="scan" placeholder="اكتب رقم الإيصال أو كود العميل المكتوب على الطرد… (ينقلك لسطره)"><span class="note" style="color:#c9cde0">Enter</span></div>

<form method="post" action="{{ route('sg.trips.receive.store', $trip->id) }}">
@csrf
<div class="card tw">
    <div class="scroll-x">
    <table class="rcv">
        <thead><tr><th></th><th>الإيصال</th><th>العميل</th><th>طلب</th><th style="width:80px">وصل</th><th style="width:72px">طرود</th><th style="width:84px">الوزن <small>(اختياري)</small></th><th style="width:96px">الشحن د.ل</th><th style="width:96px">الرف</th><th style="width:140px">نوع الطرد</th><th style="width:190px">النواقص</th><th style="text-align:left">الباقي مع الشحن</th><th style="text-align:center">جاهز؟</th><th></th></tr></thead>
        <tbody>
        @php $openIss = \Illuminate\Support\Facades\Schema::hasTable('order_issues') ? \App\Models\OrderIssue::whereIn('order_id', $orders->pluck('id'))->where('status', 'open')->get()->groupBy('order_id') : collect(); @endphp
        @foreach ($orders as $o)
            @php $ok = $o->arrived; $dataIn = $ok || $o->received_at; @endphp
            <tr class="{{ $ok ? 'ok' : '' }}" data-code="{{ $o->code }}" data-cust="{{ optional($o->customer)->code }}" data-total="{{ $o->total_price - $o->discount }}" data-paid="{{ $o->paid_price }}" data-qty="{{ $o->qty }}" data-unit="{{ $o->qty ? round(($o->total_price) / $o->qty, 2) : 0 }}">
                <td><span class="dot {{ $ok ? 'ok' : ($dataIn ? 'mid' : 'no') }}" title="{{ $ok ? 'جاهز للاستلام' : ($dataIn ? 'البيانات مسجلة' : 'لم يُفرز') }}">{{ $ok ? '✓' : ($dataIn ? '📝' : '') }}</span></td>
                <td id="r{{ $o->id }}"><span class="code">{{ $o->code }}</span>@if (!$ok && $dataIn)<small class="note" style="display:block;font-size:11.5px;color:var(--warn)">📝 بياناته مسجلة · لم يُجهَّز</small>@endif @if ($ok)<small class="note" style="display:block;font-size:11.5px">{!! $o->arrived_notified_at ? '<span style="color:var(--up)">✓ أُبلغ العميل</span>' : '<span style="color:var(--warn)">📨 لم تُرسل الرسالة</span>' !!}</small>@endif</td>
                <td class="cell2"><b>{{ optional($o->customer)->name }}</b><small class="num">{{ optional($o->customer)->code }}</small></td>
                <td class="num">{{ $o->qty }}</td>
                <td><input class="ci rq" name="rows[{{ $o->id }}][received_qty]" value="{{ old('rows.' . $o->id . '.received_qty', $o->received_qty) }}" placeholder="{{ $o->qty }}"></td>
                @php
                    // أكثر من نوع طرد: محفوظ كنص "2 كرتون + 1 كيس" — الأول في الخانة الأساسية والباقي سطور زيادة
                    $pkParts = []; $pkMainT = $o->package_type; $pkMainN = $dataIn ? $o->bags_num : '';
                    if ($dataIn && str_contains((string) $o->package_type, ' + ')) {
                        foreach (explode(' + ', (string) $o->package_type) as $pp) { if (preg_match('/^(\d+)\s+(.+)$/u', trim($pp), $mm)) { $pkParts[] = [(int) $mm[1], $mm[2]]; } }
                        if ($pkParts) { [$pkMainN, $pkMainT] = array_shift($pkParts); }
                    }
                @endphp
                <td><input class="ci pkbags" name="rows[{{ $o->id }}][bags]" value="{{ old('rows.' . $o->id . '.bags', $pkParts ? ($pkMainN + array_sum(array_column($pkParts, 0))) : $pkMainN) }}" placeholder="—" @if ($pkParts) readonly title="المجموع يتحسب من الأنواع" @endif></td>
                <td><input class="ci kg" name="rows[{{ $o->id }}][kilo]" value="{{ old('rows.' . $o->id . '.kilo', $dataIn ? $o->kilo : '') }}"></td>
                <td><input class="ci sp {{ $dataIn && $o->ship_price ? '' : 'auto' }}" name="rows[{{ $o->id }}][ship]" value="{{ old('rows.' . $o->id . '.ship', $dataIn ? $o->ship_price : '') }}"></td>
                <td><input class="ci" name="rows[{{ $o->id }}][shelf]" value="{{ old('rows.' . $o->id . '.shelf', $dataIn ? $o->storage_num : '') }}" list="shelves"></td>
                <td><div class="pkx" style="margin-top:0"><input class="ci pkn pkmain" name="rows[{{ $o->id }}][type_n]" value="{{ $pkParts ? $pkMainN : '' }}" placeholder="كم" style="width:46px;{{ $pkParts ? '' : 'display:none' }}"><select class="ci" name="rows[{{ $o->id }}][type]"><option value="">—</option>@foreach ($types as $t)<option @selected($dataIn && $pkMainT == $t)>{{ $t }}</option>@endforeach</select></div>
                    <div class="pks" data-id="{{ $o->id }}">@foreach ($pkParts as $i => [$pn, $pt])<div class="pkx"><input class="ci pkn" name="rows[{{ $o->id }}][types][{{ $i }}][n]" value="{{ $pn }}" placeholder="كم" style="width:46px"><select class="ci" name="rows[{{ $o->id }}][types][{{ $i }}][t]"><option value="">—</option>@foreach ($types as $t)<option @selected($pt == $t)>{{ $t }}</option>@endforeach</select></div>@endforeach</div>
                    <button type="button" class="pkadd" data-pkadd="{{ $o->id }}" title="نوع طرد ثاني">＋ نوع ثاني</button></td>
                <td class="missc">
                    @if ($o->missing_action)
                        <span class="note">{{ $o->missing_qty }} ناقصة · {{ $f($o->missing_value) }} · {{ ['deduct' => 'خُصمت', 'credit' => 'رصيد', 'refund' => 'أُعيد المبلغ'][$o->missing_action] ?? '' }}</span>
                    @else
                        <div class="mz" hidden style="display:flex;gap:4px"><input class="ci mv" name="rows[{{ $o->id }}][miss_value]" value="{{ old('rows.' . $o->id . '.miss_value', $o->missing_value) }}" placeholder="قيمتها" style="width:76px"><select class="ci" name="rows[{{ $o->id }}][miss_action]" style="width:100px"><option value="later">يُقرَّر لاحقاً</option><option value="deduct">تُخصم</option><option value="credit">رصيد له</option></select></div>
                        <span class="note mzn">—</span>
                    @endif
                </td>
                <td style="text-align:left"><b class="num remc">—</b><small class="note warnq" style="display:block" hidden>⚠ قطع ناقصة</small></td>
                <td style="text-align:center">@if ($ok)<span class="chip u">جاهز</span>@else<input type="checkbox" class="rdy" name="rows[{{ $o->id }}][ready]" value="1" {{ $dataIn ? 'checked' : '' }} style="width:20px;height:20px" title="يصبح جاهزاً عند الضغط على «جاهز للاستلام»">@endif</td>
                <td>@can('order-edit')<button type="button" class="btn ghost sm" data-issue="{{ route('sg.issues.store', $o) }}" data-issue-code="{{ $o->code }}" data-issue-nowa="1" title="مشكلة: نواقص / لم يصل / أخرى" @if (isset($openIss[$o->id])) style="color:#b3261e;border-color:#f3b8b2" @endif>{{ isset($openIss[$o->id]) ? '⚠ فيه مشكلة' : '⚠ مشكلة' }}</button>@endcan
                    @if (isset($openIss[$o->id]))@foreach ($openIss[$o->id] as $is)<small style="display:block;color:#b3261e;font-size:11.5px;max-width:180px">{{ $is->typeLabel() }}{{ $is->qty ? ' · ' . $is->qty . ' قطعة' : '' }}{{ $is->note ? ' — ' . \Illuminate\Support\Str::limit($is->note, 50) : '' }}</small>@endforeach @endif</td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
    <datalist id="shelves">@foreach ($shelves as $sh)<option value="{{ $sh }}">{{ ($used[$sh] ?? 0) ? 'فيه ' . $used[$sh] : 'فارغ' }}</option>@endforeach</datalist>
    <div class="tw-f">
        <div><span class="note">الوزن المسجل</span> <b class="num" id="tw">0</b> كيلو</div>
        <div><span class="note">شحن على العملاء</span> <b class="num plus" id="ts">0</b></div>
        <span class="note" style="margin-inline-start:auto">سجّل البيانات أثناء الفرز، ثم اجعل المحدد «جاهز للاستلام» · لا تُرسل رسالة قبل المراجعة</span>
        <button class="btn ghost lg" name="after" value="stay">💾 حفظ البيانات</button>
        <button class="btn green lg" name="after" value="ready">✓ جاهز للاستلام ←</button>
    </div>
</div>
</form>
@include('sg.partials.issue-modal')
@endsection
@push('script')
<template id="pkTpl"><div class="pkx"><input class="ci pkn" data-n placeholder="كم" style="width:46px"><select class="ci" data-t><option value="">—</option>@foreach ($types as $t)<option>{{ $t }}</option>@endforeach</select><button type="button" class="pkadd" data-pkdel style="margin:0">✕</button></div></template>
<script>
// ＋ نوع طرد ثاني: كل نوع وكم طرد منه
// المجموع يجي أوتوماتيك في خانة «طرود»
const pkSum = tr => { const extra = tr.querySelectorAll('.pks .pkx').length, bags = tr.querySelector('.pkbags'), main = tr.querySelector('.pkmain');
  if (!extra) { main.style.display = 'none'; bags.readOnly = false; return; }
  main.style.display = ''; bags.readOnly = true;
  bags.value = [...tr.querySelectorAll('.pkn')].filter(x => x.offsetParent !== null || x === main).reduce((a, x) => a + (parseInt(x.value) || 0), 0) || ''; };
document.addEventListener('click', e => {
  const d = e.target.closest('[data-pkdel]'); if (d) { const tr = d.closest('tr'); d.parentElement.remove(); pkSum(tr); return; }
  const b = e.target.closest('[data-pkadd]'); if (!b) return;
  const tr = b.closest('tr'), id = b.dataset.pkadd, box = b.parentElement.querySelector('.pks'), i = Date.now() % 100000, main = tr.querySelector('.pkmain');
  if (!box.children.length && !main.value) main.value = tr.querySelector('.pkbags').value || 1; // الأول ياخذ العدد اللي كان مكتوب
  const n = document.getElementById('pkTpl').content.firstElementChild.cloneNode(true);
  n.querySelector('[data-n]').name = 'rows[' + id + '][types][' + i + '][n]';
  n.querySelector('[data-t]').name = 'rows[' + id + '][types][' + i + '][t]';
  box.appendChild(n); pkSum(tr); n.querySelector('[data-n]').focus();
});
document.addEventListener('input', e => { if (e.target.classList.contains('pkn')) pkSum(e.target.closest('tr')); });
// 💾 مسودة: كل اللي تكتبه في الجدول يتحفظ في المتصفح — لو الصفحة تحدثت ترجع البيانات وتكمل وين كنت
(function () {
  const fm = document.querySelector('table.rcv') && document.querySelector('table.rcv').closest('form'); if (!fm) return;
  const KEY = 'rcv-draft-{{ $trip->id }}';
  const save = () => { try { const d = {}; fm.querySelectorAll('input[name^="rows["],select[name^="rows["]').forEach(i => { d[i.name] = i.type === 'checkbox' ? (i.checked ? '1' : '') : i.value; }); localStorage.setItem(KEY, JSON.stringify({ t: Date.now(), d })); } catch (x) {} };
  let tm; fm.addEventListener('input', () => { clearTimeout(tm); tm = setTimeout(save, 400); }); fm.addEventListener('change', save); window.addEventListener('sg-draft-save', save);
  try {
    const raw = JSON.parse(localStorage.getItem(KEY) || 'null');
    if (raw && raw.d && Date.now() - raw.t < 3 * 86400000 && !{{ $errors->any() || session('error') ? 'true' : 'false' }}) {
      let n = 0;
      Object.entries(raw.d).forEach(([k, v]) => { const i = fm.querySelector('[name="' + CSS.escape(k) + '"]'); if (!i) return;
        if (i.type === 'checkbox') { if (v && !i.checked) { i.checked = true; n++; } } else if (v !== '' && i.value !== v) { i.value = v; i.dispatchEvent(new Event('input', { bubbles: true })); n++; } });
      if (n) { const b = document.createElement('div'); b.className = 'hint mb'; b.innerHTML = '💾 رجّعنا البيانات اللي كنت تكتب فيها (' + n + ' خانة) — كمّل وين كنت، وبعدين احفظ. <a href="#" style="text-decoration:underline">امسح المسودة</a>';
        b.querySelector('a').onclick = e => { e.preventDefault(); localStorage.removeItem(KEY); location.reload(); }; fm.parentNode.insertBefore(b, fm); }
    }
  } catch (x) {}
  // لما تحفظ (💾 حفظ ولا ✓ جاهز) والحفظ ينجح، المسودة تتمسح
  fm.addEventListener('submit', () => { try { sessionStorage.setItem(KEY + '-sent', '1'); } catch (x) {} });
  try { if (sessionStorage.getItem(KEY + '-sent') && {{ session('success') ? 'true' : 'false' }}) { localStorage.removeItem(KEY); sessionStorage.removeItem(KEY + '-sent'); } } catch (x) {}
})();
// ✓ أي سطر تكتب فيه بيانات يتعلّم «جاهز؟» أوتوماتيك (تقدر تشيله لو مش جاهز)
document.addEventListener('input', e => { const tr = e.target.closest('table.rcv tbody tr'); if (!tr || e.target.classList.contains('rdy')) return; const r = tr.querySelector('.rdy'); if (r && !r.dataset.manual) r.checked = true; });
document.addEventListener('change', e => { if (e.target.classList.contains('rdy')) e.target.dataset.manual = '1'; else { const tr = e.target.closest('table.rcv tbody tr'); const r = tr && tr.querySelector('.rdy'); if (r && !r.dataset.manual) r.checked = true; } });
</script>
<script>
(function () {
  const PER = {{ $perKg }}, MIN = {{ $minShip }};
  const rows = [...document.querySelectorAll('table.rcv tbody tr')];
  function calc() {
    let tw = 0, ts = 0;
    rows.forEach(tr => {
      const kg = tr.querySelector('.kg'), sp = tr.querySelector('.sp'), rq = tr.querySelector('.rq');
      const k = parseFloat(kg.value);
      if (sp.classList.contains('auto') && !isNaN(k)) sp.value = Math.max(MIN, Math.round(k * PER));
      const ship = parseFloat(sp.value) || 0;
      if (!isNaN(k)) { tw += k; ts += ship; }
      const mvv = tr.querySelector('.mz:not([hidden]) .mv'), act = tr.querySelector('.mz:not([hidden]) select');
      const ded = mvv && act && act.value === 'deduct' ? (parseFloat(mvv.value) || 0) : 0;
      const rem = (+tr.dataset.total) + ship - (+tr.dataset.paid) - ded;
      tr.querySelector('.remc').textContent = rq.value !== '' || !isNaN(k) ? (rem > 0 ? SG.fmt(rem) : '✓ 0') : '—';
      tr.querySelector('.remc').style.color = rem > 0 ? 'var(--down)' : 'var(--up)';
      const short = rq.value !== '' && +rq.value < +tr.dataset.qty;
      rq.classList.toggle('warn', short); tr.querySelector('.warnq').hidden = !short;
      const mz = tr.querySelector('.mz'), mzn = tr.querySelector('.mzn');
      if (mz) { mz.hidden = !short; mz.style.display = short ? 'flex' : 'none'; mzn.hidden = short;
        const mv = mz.querySelector('.mv'); if (short && !mv.dataset.touched) mv.value = Math.round((+tr.dataset.qty - +rq.value) * +tr.dataset.unit);
        mzn.textContent = '—'; }
    });
    document.getElementById('tw').textContent = SG.fmt2(tw); document.getElementById('ts').textContent = SG.fmt(ts);
  }
  rows.forEach(tr => {
    const mv = tr.querySelector('.mv'); if (mv) mv.addEventListener('input', () => mv.dataset.touched = 1);
    tr.querySelector('.sp').addEventListener('input', e => e.target.classList.remove('auto'));
    tr.querySelectorAll('input,select').forEach(i => { i.addEventListener('input', calc); i.addEventListener('change', calc); });
    tr.querySelector('.rq').addEventListener('focus', e => setTimeout(() => e.target.select(), 0));
    tr.querySelector('.rq').addEventListener('input', e => { const c = tr.querySelector('.rdy'); if (c && e.target.value !== '') c.checked = true; });
  });
  document.getElementById('scan').addEventListener('keydown', e => {
    if (e.key !== 'Enter') return;
    e.preventDefault();
    const v = e.target.value.trim(); if (!v) return;
    const tr = rows.find(r => r.dataset.code === v || r.dataset.code.endsWith(v) || r.dataset.cust === v);
    if (tr) { tr.scrollIntoView({ block: 'center' }); tr.classList.add('flash'); setTimeout(() => tr.classList.remove('flash'), 1500); tr.querySelector('.rq').focus(); e.target.value = ''; }
  });
  calc();
})();
</script>
@endpush
