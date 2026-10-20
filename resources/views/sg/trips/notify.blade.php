@extends('sg.layout')
@section('title', 'مراجعة الرسائل' . ($trip ? ' · ' . $trip->trip_code : ''))
@section('bodyClass', 'soft')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
@push('style')
<style>
.nt { display: grid; grid-template-columns: 1.05fr 1fr; gap: 0; border: 1px solid var(--line); border-radius: 14px; background: #fff; margin-bottom: 12px; overflow: hidden; }
.nt.off { opacity: .55; }
.nt .l { padding: 14px 16px; border-inline-end: 1px solid var(--line-soft); }
.nt .r { padding: 12px 14px; background: #f7faf8; }
.nt .hd { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.nt .hd input[type=checkbox] { width: 20px; height: 20px; accent-color: var(--brand); }
.kv { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
.kv div { background: #fafbfc; border: 1px solid var(--line-soft); border-radius: 10px; padding: 7px 10px; }
.kv small { display: block; color: var(--ink-3); font-size: 11.5px; }
.kv b { font-family: InterNum; font-size: 15px; }
.kv b.miss { color: var(--warn); }
.nt textarea { width: 100%; min-height: 150px; border: 1px solid #cfe5d6; border-radius: 12px; padding: 10px 12px; font: inherit; font-size: 13.5px; line-height: 1.75; background: #fff; resize: vertical; }
.nt textarea.edited { border-color: #f0c674; background: #fffdf3; }
.bar { position: sticky; bottom: 0; background: #fff; border-top: 1px solid var(--line); padding: 12px 16px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap; border-radius: 14px 14px 0 0; box-shadow: 0 -6px 18px rgba(0,0,0,.05); }
@media (max-width: 900px) { .nt { grid-template-columns: 1fr; } .nt .l { border-inline-end: 0; border-bottom: 1px solid var(--line-soft); } .kv { grid-template-columns: repeat(2, 1fr); } }
</style>
@endpush
@section('content')
@if ($trip)<div class="crumb"><a href="{{ route('sg.trips.index') }}">الرحلات</a> › <a href="{{ route('finance.trips.show', $trip->id) }}">{{ $trip->trip_code }}</a> › <a href="{{ route('sg.trips.receive', $trip->id) }}">استلام</a> › مراجعة الرسائل</div>
@else<div class="crumb"><a href="{{ route('sg.shipping') }}">الشحن</a> › مراجعة رسائل الوصول</div>@endif
<div class="top">
    <div><h1>مراجعة رسائل «وصلت بضاعتك»</h1><div class="sub">{{ $orders->count() }} طلب بانتظار الإرسال · أُرسل سابقاً {{ $sentCount }} · راجع البيانات، وعدّلها أو عدّل نص الرسالة، ثم أرسل</div></div>
    <div class="acts"><a class="btn ghost" href="{{ $trip ? route('sg.trips.receive', $trip->id) : route('sg.shipping', ['stage' => 'arrived']) }}">✎ تعديل بيانات الاستلام</a><button class="sg-burger">☰</button></div>
</div>

@if (!$tpl)
    <div class="warnbox mb">قالب رسالة «وصلت بضاعتك» غير موجود · أضفه من الإعدادات ← قوالب الواتساب، أو اكتب نص الرسالة يدوياً لكل طلب.</div>
@elseif (!($tpl->enabled ?? true))
    <div class="warnbox mb">قالب «وصلت بضاعتك» موقوف في الإعدادات، لكن الإرسال من هذه الصفحة يتم يدوياً بعد مراجعتك.</div>
@endif

@if ($orders->isEmpty())
    <div class="card"><div class="empty"><span class="ic">✅</span>لا توجد رسائل بانتظار الإرسال</div></div>
@else
<form method="post" action="{{ $trip ? route('sg.trips.notify.send', $trip->id) : route('sg.shipping.notify.send') }}" id="nf">
@csrf
<div style="display:flex;gap:10px;align-items:center;margin:0 2px 10px"><label class="ck"><input type="checkbox" id="all" checked> تحديد الكل</label><span class="note">البيانات الخاطئة؟ اضغط «تعديل» بجانب الطلب، ثم ارجع لهذه الصفحة — الرسالة تتحدث تلقائياً</span></div>
@foreach ($orders as $o)
    @php $c = $o->customer; $short = max(0, (int) $o->qty - (int) ($o->received_qty ?? $o->qty)); $rem = max(0, $o->remaining()); @endphp
    <div class="nt" data-id="{{ $o->id }}">
        <div class="l">
            <div class="hd">
                <input type="checkbox" name="ids[]" value="{{ $o->id }}" class="pick" checked>
                <div style="flex:1"><b>{{ optional($c)->name }}</b> <span class="note num">{{ optional($c)->code }}</span><div class="note" style="font-size:12.5px"><span class="code">{{ $o->code }}</span> · {{ optional($c)->phone_1 ?: 'لا يوجد رقم' }}</div></div>
                <a class="btn ghost sm" href="{{ $trip ? route('sg.trips.receive', $trip->id) . '#r' . $o->id : route('sg.shipping.edit', $o->id) }}">✎ تعديل</a>
            </div>
            <div class="kv">
                <div><small>وصل / المطلوب</small><b class="{{ $short ? 'miss' : '' }}">{{ $o->received_qty ?? $o->qty }} / {{ $o->qty }}</b></div>
                <div><small>الطرود</small><b>{{ $o->bags_num ?: 1 }}</b></div>
                <div><small>الوزن</small><b>{{ $o->kilo ? $f($o->kilo) . ' كغ' : '—' }}</b></div>
                <div><small>الشحن</small><b>{{ $f($o->ship_price) }}</b></div>
                <div><small>الرف</small><b>{{ $o->storage_num ?: '—' }}</b></div>
                <div><small>الباقي على العميل</small><b style="color:{{ $rem > 0 ? 'var(--down)' : 'var(--up)' }}">{{ $f($rem) }}</b></div>
            </div>
            @if ($short)<div class="note" style="margin-top:8px;color:var(--warn)">⚠ ناقص {{ $short }} قطعة{{ $o->missing_value ? ' · قيمتها ' . $f($o->missing_value) : '' }}{{ $o->missing_action === 'deduct' ? ' (خُصمت)' : ($o->missing_action === 'credit' ? ' (أُضيفت للرصيد)' : '') }}</div>@endif
            @if (!$o->kilo && !$o->ship_price)<div class="note" style="margin-top:8px;color:var(--warn)">⚠ لم يُسجّل وزن أو سعر شحن</div>@endif
        </div>
        <div class="r">
            <div class="note" style="margin-bottom:6px;display:flex;justify-content:space-between">نص الرسالة <a href="#" class="reset" style="color:var(--s1)" hidden>↺ إعادة النص الأصلي</a></div>
            <textarea name="msg[{{ $o->id }}]" data-orig="{{ $previews[$o->id] }}">{{ $previews[$o->id] }}</textarea>
        </div>
    </div>
@endforeach
<div class="bar">
    <span>المحدد: <b class="num" id="cnt">{{ $orders->count() }}</b></span>
    <button class="btn ghost" name="mark_only" value="1" style="margin-inline-start:auto" onclick="return confirm('تعليمها كمُبلَّغة دون إرسال أي رسالة؟')">تعليم كمُبلَّغ دون إرسال</button>
    <button class="btn green lg">📨 إرسال الرسائل المحددة</button>
</div>
</form>
@endif
@if ($trip)<script>try { if (sessionStorage.getItem('rcv-draft-{{ $trip->id }}-sent')) { localStorage.removeItem('rcv-draft-{{ $trip->id }}'); sessionStorage.removeItem('rcv-draft-{{ $trip->id }}-sent'); } } catch (x) {}</script>@endif
@endsection
@push('script')
<script>
(function () {
  const picks = [...document.querySelectorAll('.pick')], all = document.getElementById('all');
  function upd() { let n = 0; picks.forEach(p => { p.closest('.nt').classList.toggle('off', !p.checked); p.closest('.nt').querySelector('textarea').disabled = !p.checked; if (p.checked) n++; }); const c = document.getElementById('cnt'); if (c) c.textContent = n; }
  picks.forEach(p => p.addEventListener('change', upd));
  if (all) all.addEventListener('change', () => { picks.forEach(p => p.checked = all.checked); upd(); });
  document.querySelectorAll('.nt textarea').forEach(t => {
    const r = t.closest('.r').querySelector('.reset');
    t.addEventListener('input', () => { const e = t.value !== t.dataset.orig; t.classList.toggle('edited', e); r.hidden = !e; });
    r.addEventListener('click', ev => { ev.preventDefault(); t.value = t.dataset.orig; t.dispatchEvent(new Event('input')); });
  });
})();
</script>
@endpush
