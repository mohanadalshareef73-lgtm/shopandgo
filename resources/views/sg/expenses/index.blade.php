@extends('sg.layout')
@section('title', 'المصروفات')
@php
    use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n);
    $top = $byCat->first();
    $catTotal = max(1, $byCat->sum('total'));
    $cols = ['var(--s2)', 'var(--s1)', 'var(--s3)', 'var(--s7)', 'var(--s4)', 'var(--brand)'];
    $defIcons = ['إيجار' => '🏠', 'انترنت' => '🌐', 'كهرباء' => '💡', 'تغليف' => '📦', 'أكياس' => '📦', 'إعلان' => '📣', 'ضيافة' => '☕', 'مواصلات' => '🚗', 'رواتب' => '👥'];
    $iconOf = function ($c) use ($defIcons) { if (!$c) return '•'; if ($c->icon) return $c->icon; foreach ($defIcons as $k => $i) if (str_contains($c->name, $k)) return $i; return '🧾'; };
@endphp
@push('style')
<style>
.cats { display: flex; flex-wrap: wrap; gap: 6px; }
.cats label input { position: absolute; opacity: 0; pointer-events: none; }
.cats label span { display: inline-flex; gap: 6px; align-items: center; border: 1px solid var(--line); border-radius: 10px; padding: 8px 11px; font-size: 13.5px; cursor: pointer; background: #fff; }
.cats label input:checked + span { border: 2px solid var(--brand); padding: 7px 10px; font-weight: 700; background: #f6f7fb; }
.wal { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 6px; }
.wal label input { position: absolute; opacity: 0; pointer-events: none; }
.wal label span { display: block; border: 1px solid var(--line); border-radius: 10px; padding: 8px 10px; font-size: 13.5px; cursor: pointer; text-align: center; }
.wal label span small { display: block; font-family: InterNum; color: var(--ink-3); font-size: 12px; }
.wal label input:checked + span { border: 2px solid var(--brand); padding: 7px 9px; font-weight: 700; background: #f6f7fb; }
.upload { border: 2px dashed var(--line); border-radius: 12px; padding: 14px; text-align: center; color: var(--ink-3); font-size: 13.5px; cursor: pointer; display: block; }
.upload input { display: none; }
.fx { display: flex; justify-content: space-between; font-size: 13.5px; padding: 5px 0; }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>{{ $mine ? 'مصروفاتي' : 'المصروفات' }}</h1><div class="sub">{{ $mine ? 'المصروفات اللي صرفتها في دوامك · تنقص من الكاش اللي معاك' : 'كل أموال طلعت على الشركة: إيجار، انترنت، تغليف، إعلانات…' }}</div></div>
    <div class="acts">@can('partner-create')@if ($partners->count())<button class="btn ghost" data-modal="mPartExp">👤 مصروف شريك</button>@endif @endcan @if (!$mine)<button class="btn" data-modal="mSalary" onclick="setSal('salary')">💰 مرتب موظف</button><button class="btn ghost" data-modal="mSalary" onclick="setSal('advance')">💸 سلفة</button><button class="btn ghost" data-modal="mBonus">🎁 مكافأة موظف</button>@endif @include('sg.partials.period')<a class="btn ghost" href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}">⬇ Excel</a><button class="sg-burger">☰</button></div>
</div>

<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">مصاريف {{ $period->label }}</div><div class="v"><span class="num">{{ $f($total) }}</span> <small>د.ل</small></div><div class="s">@include('sg.partials.chg', ['cur' => $total, 'prev' => $prev, 'invert' => true]) عن {{ $period->prevLabel }} ({{ $f($prev) }})</div></div>
    <div class="card kpi"><div class="lbl">متوسط في اليوم</div><div class="v"><span class="num">{{ $f($total / $days) }}</span> <small>د.ل</small></div><div class="s">{{ $days }} يوم</div></div>
    <div class="card kpi"><div class="lbl">أكبر بند</div><div class="v" style="font-size:22px">{{ $top ? optional($top['cat'])->name : '—' }}</div><div class="s">@if ($top)<span class="num">{{ $f($top['total']) }}</span> د.ل · {{ round($top['total'] / $catTotal * 100) }}%@endif</div></div>
    @can('profit-view')<div class="card kpi"><div class="lbl">من الربح</div><div class="v"><span class="num">{{ $tripProfit > 0 ? round($total / $tripProfit * 100) : 0 }}%</span></div><div class="s">المصاريف من ربح الرحلات ({{ $f($tripProfit) }})</div></div>@endcan
</div>

<div class="side-lay">
    <div>
        <div class="grid g2 mb">
            <div class="card">
                <h3>حسب النوع <span class="h-note">{{ $period->label }}</span></h3>
                @forelse ($byCat as $c)
                    <div class="hb"><div class="tt"><span>{{ $iconOf($c['cat']) }} {{ optional($c['cat'])->name }} <span class="note">({{ $c['n'] }})</span></span><b class="num">{{ $f($c['total']) }}</b></div><div class="b"><i style="width:{{ $c['total'] / $catTotal * 100 }}%;background:{{ $cols[$loop->index % 6] }}"></i></div></div>
                @empty
                    <div class="empty" style="padding:30px">لا يوجد مصاريف في الفترة</div>
                @endforelse
            </div>
            <div class="card">
                <h3>آخر 6 شهور</h3>
                <svg class="chart" data-bars data-h="210" data-w="420" data-chart='{{ json_encode(array_merge($six, ['unit' => 'د.ل', 'color' => '#eb6834', 'soft' => '#f5b89c', 'highlightLast' => true])) }}'></svg>
            </div>
        </div>

        <div class="card tw">
            <form class="tw-h" method="get">
                <input type="hidden" name="p" value="{{ $period->preset }}">
                <h3>سجل المصروفات</h3>
                <div class="sx"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/></svg><input name="q" value="{{ request('q') }}" placeholder="بحث في الملاحظات…"></div>
                <select name="desc_id" class="pill" data-autosubmit><option value="">النوع: الكل</option>@foreach ($cats as $c)<option value="{{ $c->id }}" @selected(request('desc_id') == $c->id)>{{ $c->name }}</option>@endforeach</select>
                <select name="payment_id" class="pill" data-autosubmit><option value="">الخزينة: الكل</option>@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected(request('payment_id') == $pm->id)>{{ $pm->name }}</option>@endforeach</select>
                <select name="created_id" class="pill" data-autosubmit><option value="">الموظف: الكل</option>@foreach ($users as $id => $n)<option value="{{ $id }}" @selected(request('created_id') == $id)>{{ $n }}</option>@endforeach</select>
            </form>
            <div class="scroll-x">
            <table class="t"><thead><tr><th>التاريخ</th><th>النوع</th><th>التفاصيل</th><th>من خزينة</th><th>سجّله</th><th class="n">المبلغ</th><th></th></tr></thead><tbody>
            @forelse ($list as $e)
                <tr>
                    <td class="note num">{{ $e->date }}</td>
                    <td>{{ $iconOf($e->desc) }} {{ optional($e->desc)->name }}</td>
                    <td class="note wrap" style="max-width:240px">{{ $e->note }} @if ($e->attachment)<a href="{{ asset('storage/' . $e->attachment) }}" target="_blank" style="color:var(--s1)">📎 الفاتورة</a>@endif</td>
                    <td><span class="chip n">{{ optional($e->payment)->name }}</span></td>
                    <td class="note">{{ $users[$e->created_id] ?? optional(\App\Models\User::find($e->created_id))->name }}</td>
                    <td class="n"><b class="num minus">−{{ $f($e->cost) }}</b> <span class="note">{{ TS::sign($e->currency ?: 'dinar') }}</span></td>
                    <td>@can('purchase-delete')<form method="post" action="{{ route('sg.expenses.destroy', $e) }}" data-confirm="حذف المصروف وإرجاع المبلغ للخزينة؟">@csrf @method('DELETE')<button class="more" title="حذف">×</button></form>@endcan</td>
                </tr>
            @empty
                <tr><td colspan="7"><div class="empty"><span class="ic">🧾</span>لا يوجد</div></td></tr>
            @endforelse
            </tbody></table>
            </div>
            <div class="tw-f"><span>المجموع <b class="num minus">{{ $f($total) }}</b> د.ل</span>@include('sg.partials.pager', ['p' => $list])</div>
        </div>
    </div>

    <div>
    @can('purchase-create')
    <form class="card sticky" method="post" action="{{ route('sg.expenses.store') }}" enctype="multipart/form-data">
        @csrf
        <h3>مصروف جديد</h3>
        <div class="f"><label class="l">المبلغ</label><input name="cost" id="ecost" class="inp big" value="{{ old('cost') }}" required inputmode="decimal"></div>
        <div class="f"><label class="l">النوع</label>
            <div class="cats">
                @foreach ($cats as $c)<label><input type="radio" name="desc_id" value="{{ $c->id }}" @checked(old('desc_id') == $c->id)><span>{{ $iconOf($c) }} {{ $c->name }}</span></label>@endforeach
                <label><input type="radio" name="desc_id" value="" id="newCatR"><span>＋ نوع جديد</span></label>
            </div>
            <input name="new_cat" id="newCat" class="inp mt" placeholder="اسم النوع الجديد" hidden>
        </div>
        <div class="f"><label class="l">من خزينة</label>
            <div class="wal">
                @foreach ($payments as $pm)
                    <label><input type="radio" name="payment_id" value="{{ $pm->id }}" @checked(old('payment_id', $cashId) == $pm->id)><span>{{ $pm->name }}@if (!$mine)<small>{{ $f($balances[$pm->id]['dinar'] ?? 0) }}</small>@endif</span></label>
                @endforeach
            </div>
        </div>
        <div class="grid g2 f">
            <div><label class="l">العملة</label><select name="currency" class="inp">@foreach (TS::CURRENCY_NAMES as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select></div>
            <div><label class="l">التاريخ</label><input type="date" name="date" class="inp" value="{{ old('date', now()->toDateString()) }}"></div>
        </div>
        <div class="f"><label class="l">ملاحظة</label><input name="note" class="inp" value="{{ old('note') }}" placeholder="مثلا: فاتورة كهرباء {{ \App\Services\Period::MONTHS[now()->month - 1] }}"></div>
        <div class="f"><label class="upload"><input type="file" name="attachment" accept="image/*,application/pdf" onchange="this.parentNode.lastChild.textContent=' ' + (this.files[0]?.name || '')">📎 صوّر الفاتورة (اختياري)<span></span></label></div>
        <button class="btn block lg" id="ebtn">حفظ المصروف</button>
        <div class="note mt" style="text-align:center">ينخصم من الخزينة طول ويظهر في المالية والأرباح</div>
        @if ($fixed->count())
        <div style="border-top:1px dashed var(--line);margin-top:14px;padding-top:12px">
            <div style="font-weight:700;font-size:14px;margin-bottom:8px">مصاريف ثابتة كل شهر</div>
            @foreach ($fixed as $x)
                <div class="fx"><span>{{ $iconOf($x->cat) }} {{ $x->cat->name }} · يوم {{ $x->cat->monthly_day }}</span>@if ($x->paid)<span class="chip u">✓ اندفع</span>@elseif ($x->left >= 0)<span class="chip w">باقي {{ $x->left }} يوم</span>@else<span class="chip d">متأخر {{ abs($x->left) }} يوم</span>@endif</div>
            @endforeach
        </div>
        @endif
        <div style="margin-top:10px"><button type="button" class="btn ghost sm block" data-modal="catsM">⚙ أنواع المصروفات</button></div>
    </form>
    @endcan
    </div>
</div>

@can('purchase-create')
<div class="modal-bg" id="catsM"><div class="modal" style="max-width:560px">
    <h3>أنواع المصروفات</h3>
    <div class="note mb">"يوم الشهر" = مصروف ثابت يتكرر (إيجار، انترنت…) لكي يذكرك إذا لم يُدفع</div>
    @foreach ($cats as $c)
        <form method="post" action="{{ route('sg.expenses.cats.update', $c) }}" style="display:grid;grid-template-columns:56px 1fr 90px auto;gap:6px;margin-bottom:6px">@csrf @method('PUT')
            <input name="icon" class="inp" value="{{ $c->icon }}" placeholder="{{ $iconOf($c) }}" style="height:38px;text-align:center"><input name="name" class="inp" value="{{ $c->name }}" style="height:38px"><input name="monthly_day" class="inp numr" value="{{ $c->monthly_day }}" placeholder="يوم" style="height:38px"><button class="btn ghost sm">حفظ</button>
        </form>
    @endforeach
    <form method="post" action="{{ route('sg.expenses.cats.store') }}" style="display:grid;grid-template-columns:56px 1fr 90px auto;gap:6px;margin-top:12px;border-top:1px dashed var(--line);padding-top:12px">@csrf
        <input name="icon" class="inp" placeholder="🧾" style="height:38px;text-align:center"><input name="name" class="inp" placeholder="نوع جديد" required style="height:38px"><input name="monthly_day" class="inp numr" placeholder="يوم" style="height:38px"><button class="btn sm">إضافة</button>
    </form>
    <button type="button" class="btn ghost block mt" data-close>إغلاق</button>
</div></div>
@endcan
@can('partner-create')
<div class="modal-bg" id="mPartExp"><div class="modal">
    <h3>مصروف شخصي لشريك <span class="h-note">يتسجل دين عليه</span></h3>
    <form method="post" action="{{ route('finance.partners.transactions.store') }}" onsubmit="const s=this.querySelector('[name=pid]');const a=this.querySelector('[name=amt]');this.querySelector('.am').name='amounts['+s.value+']';this.querySelector('.am').value=a.value;">
        @csrf <input type="hidden" name="type" value="expense"><input type="hidden" name="currency" value="dinar"><input type="hidden" class="am">
        <div class="f"><label class="l">الشريك</label><div class="opts">@foreach ($partners as $pt)<label><input type="radio" name="pid" value="{{ $pt->id }}" @checked($loop->first)><span>{{ $pt->name }}</span></label>@endforeach</div></div>
        <div class="grid g2 f"><div><label class="l">المبلغ (د.ل)</label><input name="amt" class="inp numr" required></div><div><label class="l">التاريخ</label><input type="date" name="date" class="inp" value="{{ now()->toDateString() }}"></div></div>
        <div class="f"><label class="l">النوع / السبب</label><input name="note" class="inp" placeholder="بنزين، هاتف، غرض شخصي…" required></div>
        <div class="f"><label class="l">طلع من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }}</option>@endforeach</select></div>
        <div class="note mb">ما يتحسبش مصروف على الشركة. يتسجل دين على الشريك، وتقدر تخصمه من نصيبه وقت توزيع الفائض أو تأجله.</div>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endcan
@if (!$mine)
<div class="modal-bg" id="mSalary"><div class="modal" style="max-width:460px">
    <h3 id="salTitle">💰 مرتب موظف</h3>
    <form method="post" action="{{ route('sg.expenses.salary') }}" onsubmit="return confirm(this.kind.value === 'salary' ? 'نسجلو المرتب؟' : 'نسجلو السلفة على الموظف؟')">@csrf
        <input type="hidden" name="kind" id="salKind" value="salary">
        <div class="f"><label class="l">الموظف</label><select name="staff_id" id="salStaff" class="inp" required><option value="">— اختار —</option>@foreach ($staff as $u)<option value="{{ $u->id }}" data-adv="{{ $openAdv[$u->id] ?? 0 }}">{{ $u->name }}</option>@endforeach</select></div>
        <div id="salAdv" hidden style="background:#fff4e6;border:2px solid #e8590c;border-radius:12px;padding:10px 12px;margin-bottom:10px">
            ⚠ عنده سلف ما تخصمتش: <b class="num" id="salAdvN"></b> د.ل
            <label class="ck" style="margin-top:6px;font-weight:800"><input type="checkbox" name="deduct" value="1" checked> اخصمها من المرتب</label>
        </div>
        <div class="grid g2 f"><div><label class="l" id="salAmtL">المرتب (د.ل)</label><input name="cost" id="salAmt" class="inp numr" required inputmode="decimal" style="font-size:20px;font-weight:800"></div>
            <div><label class="l">التاريخ</label><input type="date" name="date" class="inp" value="{{ now()->toDateString() }}"></div></div>
        <div class="note" id="salNet"></div>
        <div class="f"><label class="l">من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }}</option>@endforeach</select></div>
        <div class="f"><label class="l">ملاحظة <span class="note">(اختياري)</span></label><input name="note" class="inp" placeholder="مثلاً: مرتب شهر {{ \App\Services\Period::MONTHS[now()->month - 1] }}"></div>
        <div style="display:flex;gap:8px"><button class="btn green">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
<script>
window.setSal = function (k) {
  document.getElementById('salKind').value = k;
  document.getElementById('salTitle').textContent = k === 'salary' ? '💰 مرتب موظف' : '💸 سلفة لموظف';
  document.getElementById('salAmtL').textContent = k === 'salary' ? 'المرتب (د.ل)' : 'السلفة (د.ل)';
  updSal();
};
function updSal() {
  const sel = document.getElementById('salStaff'), k = document.getElementById('salKind').value, adv = +(sel.selectedOptions[0]?.dataset.adv || 0), box = document.getElementById('salAdv');
  box.hidden = !(k === 'salary' && adv > 0); document.getElementById('salAdvN').textContent = adv;
  const a = +document.getElementById('salAmt').value || 0, ded = box.querySelector('input').checked;
  document.getElementById('salNet').textContent = k === 'salary' && adv > 0 && a ? ('يستلم صافي: ' + Math.max(0, a - (ded ? adv : 0)).toFixed(2) + ' د.ل') : '';
}
document.getElementById('salStaff').addEventListener('change', updSal); document.getElementById('salAmt').addEventListener('input', updSal); document.querySelector('#salAdv input').addEventListener('change', updSal);
</script>
@endif
<div class="modal-bg" id="mBonus"><div class="modal">
    <h3>مكافأة / زيادة لموظف <span class="h-note">تتسجل مصروف «مكافآت الموظفين»</span></h3>
    <form method="post" action="{{ route('sg.expenses.store') }}" onsubmit="this.querySelector('[name=note]').value=this.querySelector('[name=btype]').value+' — '+this.querySelector('[name=bemp]').value+(this.querySelector('[name=bnote]').value?' · '+this.querySelector('[name=bnote]').value:'')">
        @csrf <input type="hidden" name="new_cat" value="مكافآت الموظفين"><input type="hidden" name="note"><input type="hidden" name="currency" value="dinar">
        <div class="grid g2 f"><div><label class="l">الموظف</label><select name="bemp" class="inp">@foreach ($staff as $u)<option>{{ $u->name }}</option>@endforeach</select></div>
            <div><label class="l">النوع</label><select name="btype" class="inp"><option>مكافأة شهرية</option><option>زيادة راتب</option><option>مكافأة أداء</option><option>حافز</option><option>أخرى</option></select></div></div>
        <div class="grid g2 f"><div><label class="l">المبلغ (د.ل)</label><input name="cost" class="inp numr" required></div><div><label class="l">التاريخ</label><input type="date" name="date" class="inp" value="{{ now()->toDateString() }}"></div></div>
        <div class="f"><label class="l">من خزينة</label><select name="payment_id" class="inp">@foreach ($payments as $pm)<option value="{{ $pm->id }}" @selected($pm->id == $cashId)>{{ $pm->name }}</option>@endforeach</select></div>
        <div class="f"><label class="l">ملاحظة <span class="note">(اختياري)</span></label><input name="bnote" class="inp" placeholder="مثلاً: شهر 10"></div>
        <div style="display:flex;gap:8px"><button class="btn">حفظ</button><button type="button" class="btn ghost" data-close>إلغاء</button></div>
    </form>
</div></div>
@endsection
@push('script')
<script>
(function () {
  const c = document.getElementById('ecost'); if (!c) return;
  const b = document.getElementById('ebtn'), nc = document.getElementById('newCat');
  const upd = () => b.textContent = 'حفظ المصروف' + (+c.value ? ' · ' + SG.fmt2(c.value) + ' د.ل' : '');
  c.addEventListener('input', upd); upd();
  document.querySelectorAll('input[name=desc_id]').forEach(r => r.addEventListener('change', () => { nc.hidden = r.id !== 'newCatR' || !r.checked; if (!nc.hidden) nc.focus(); }));
})();
</script>
@endpush
