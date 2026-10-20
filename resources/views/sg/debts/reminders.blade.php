@extends('sg.layout')
@section('title', 'تذكير الديون')
@section('bodyClass', 'soft')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
@push('style')
<style>
.rm { display: grid; grid-template-columns: 28px 1fr 130px 1.3fr; gap: 14px; align-items: start; padding: 14px 16px; border-top: 1px solid var(--line-soft); }
.rm:first-of-type { border-top: 0; }
.rm .pv { background: #eefaf2; border-radius: 12px 12px 12px 4px; padding: 10px 12px; font-size: 13px; white-space: pre-line; color: #1d3b2a; line-height: 1.6; }
.rm.off { opacity: .45; }
.rm input[type=checkbox] { width: 20px; height: 20px; margin-top: 3px; accent-color: var(--brand); }
@media (max-width: 900px) { .rm { grid-template-columns: 28px 1fr; } .rm .pv { grid-column: 2; } }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.debts') }}">الديون</a> › تذكيرات تستنى تأكيدك</div>
<div class="top">
    <div><h1>تذكير الديون</h1><div class="sub">النظام يجهز القائمة كل يوم صباحاً، وما يُرسل أي شيء حتى أنت تأكد</div></div>
    <div class="acts"><a class="btn ghost" href="{{ route('sg.messages') }}">✎ تعديل نص الرسالة</a><button class="sg-burger">☰</button></div>
</div>

<div class="hint mb">القاعدة: الذي عليه أكثر من <b class="num">{{ $rules['min'] }}</b> د.ل، ودينه مضى عليه <b class="num">{{ $rules['days'] }}</b> يوم، ولم يأخذ تذكير من <b class="num">{{ $rules['every'] }}</b> أيام. يمكنك أن تبدلها من <a href="{{ route('sg.settings', ['tab' => 'remind']) }}" style="color:var(--s1)">الإعدادات</a>.</div>

<div class="seg mb" style="max-width:420px">
    <a href="{{ route('sg.debts.reminders') }}" class="{{ !$all ? 'on' : '' }}">يستحقوا تذكير</a>
    <a href="{{ route('sg.debts.reminders', ['all' => 1]) }}" class="{{ $all ? 'on' : '' }}">كل الذي عليهم ديون</a>
</div>

<form method="post" action="{{ route('sg.debts.remind') }}" class="card tw" id="rmF">
    @csrf
    <div class="tw-h">
        <label class="ck"><input type="checkbox" id="allChk" checked> اختر الكل</label>
        <span class="note" id="selN"></span>
        <div style="margin-inline-start:auto;display:flex;gap:8px">
            <button class="btn green" id="sendBtn" @disabled(!$rows->count())>✓ تأكيد وإرسال</button>
        </div>
    </div>
    @forelse ($rows as $r)
        <div class="rm">
            <input type="checkbox" name="ids[]" value="{{ $r->customer->id }}" class="pick" checked>
            <div>
                <b>{{ $r->customer->name }}</b> <span class="note num">{{ $r->customer->code }} · {{ $r->customer->phone_1 }}</span>
                <div class="note">{{ $r->orders->count() }} إيصال: <span class="num">{{ $r->orders->pluck('code')->take(4)->implode('، ') }}{{ $r->orders->count() > 4 ? '…' : '' }}</span></div>
                <div class="note">عمر الدين <b class="num">{{ $r->age }}</b> يوم · {{ $r->last ? 'آخر تذكير ' . \Carbon\Carbon::parse($r->last)->diffForHumans() : 'لم يأخذ تذكير قبل' }} @if ($r->gone)<span class="chip d">استلم بضاعته</span>@endif</div>
            </div>
            <div><b class="num minus" style="font-size:18px">{{ $f($r->due) }}</b> <span class="note">د.ل</span></div>
            <div class="pv">{{ \App\Services\ReminderService::debtText($r->customer, $r) ?: 'لا يوجد قالب "تذكير دين" — ضيفه من رسائل الواتساب' }}</div>
        </div>
    @empty
        <div class="empty"><span class="ic">✓</span>لا يوجد أحد يستحق تذكير الآن</div>
    @endforelse
    @if ($rows->count())<div class="tw-f"><span>المجموع <b class="num minus">{{ $f($rows->sum('due')) }}</b> د.ل على <b class="num">{{ $rows->count() }}</b> عميل</span></div>@endif
</form>
@endsection
@push('script')
<script>
(function () {
  const f = document.getElementById('rmF'), picks = [...f.querySelectorAll('.pick')], all = document.getElementById('allChk');
  const upd = () => { const n = picks.filter(p => p.checked).length; document.getElementById('selN').textContent = n + ' مختار'; document.getElementById('sendBtn').textContent = '✓ تأكيد وإرسال (' + n + ')'; picks.forEach(p => p.closest('.rm').classList.toggle('off', !p.checked)); };
  picks.forEach(p => p.addEventListener('change', upd));
  all.addEventListener('change', () => { picks.forEach(p => p.checked = all.checked); upd(); });
  f.addEventListener('submit', e => { const n = picks.filter(p => p.checked).length; if (!n || !confirm('إرسال تذكير الدين لـ ' + n + ' عميل؟')) e.preventDefault(); });
  upd();
})();
</script>
@endpush
