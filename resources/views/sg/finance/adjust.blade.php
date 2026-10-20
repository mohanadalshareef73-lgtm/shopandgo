@extends('sg.layout')
@section('title', 'ضبط الأرصدة')
@php use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
@push('style')
<style>
.adj td { vertical-align: middle; }
.adj .inp { height: 40px; max-width: 170px; }
.adj tr.zero { display: none; }
.adj.all tr.zero { display: table-row; }
.adj .df { font-weight: 800; min-width: 90px; display: inline-block; }
.adj tr.chg { background: #fffbea; }
</style>
@endpush
@section('content')
<div class="crumb"><a href="{{ route('sg.finance') }}">المالية</a> › ضبط الأرصدة</div>
<div class="top">
    <div><h1>ضبط الأرصدة (جرد)</h1><div class="sub">اكتب الرصيد الحقيقي لأي خزينة بأي عملة. الفرق يتسجل «تسوية جرد» — لا يدخل في الأرباح ولا الديون</div></div>
    <div class="acts"><button class="sg-burger">☰</button></div>
</div>

<form method="post" action="{{ route('sg.finance.adjust') }}" id="adjF">
    @csrf
    <div class="card tw">
        <div class="tw-h" style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap">
            <h3 style="margin:0">الخزائن</h3>
            <label style="display:flex;gap:6px;align-items:center;font-size:13.5px"><input type="checkbox" id="showAll"> اعرض العملات الفارغة أيضاً</label>
        </div>
        <table class="t adj" id="adjT">
            <thead><tr><th>الخزينة</th><th>العملة</th><th class="n">الرصيد في المنظومة</th><th>الرصيد الحقيقي</th><th>الفرق</th><th></th></tr></thead>
            <tbody>
            @foreach ($rows as $r)
                @php $k = $r['pm']->id . ':' . $r['cur']; $zero = abs($r['bal']) < 0.01; @endphp
                <tr class="{{ $zero ? 'zero' : '' }}" data-bal="{{ $r['bal'] }}">
                    <td><b>{{ $r['pm']->name }}</b>@if (!$r['pm']->status) <span class="chip n">موقوفة</span>@endif</td>
                    <td>{{ TS::CURRENCY_NAMES[$r['cur']] }}</td>
                    <td class="n num">{{ $f($r['bal']) }} <span class="note">{{ TS::sign($r['cur']) }}</span></td>
                    <td><input class="inp numr act" name="actual[{{ $r['pm']->id }}][{{ $r['cur'] }}]" value="{{ old('actual.' . $r['pm']->id . '.' . $r['cur']) }}" inputmode="decimal" placeholder="—"></td>
                    <td><span class="df num">—</span></td>
                    <td><button class="btn ghost sm one" name="only" value="{{ $k }}" disabled>اضبط هذا فقط</button></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    <div class="card" style="margin-top:14px;display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
        <div style="flex:1;min-width:240px"><label class="l">السبب (يتسجل مع كل حركة)</label><input name="note" class="inp" required value="{{ old('note', 'جرد ' . now()->format('d/m/Y')) }}"></div>
        <button class="btn" id="allBtn" disabled>✓ اضبط كل الأرصدة المعبأة (<span id="cnt">0</span>)</button>
    </div>
</form>

@if ($recent->count())
<div class="card tw" style="margin-top:14px">
    <div class="tw-h"><h3>آخر عمليات الضبط</h3></div>
    <table class="t"><thead><tr><th>الوقت</th><th>التفاصيل</th></tr></thead><tbody>
        @foreach ($recent as $h)<tr><td class="num" style="white-space:nowrap">{{ optional($h->created_at)->format('Y-m-d g:i a') }}</td><td>{{ $h->note ?? $h->description ?? '' }}</td></tr>@endforeach
    </tbody></table>
</div>
@endif
@endsection
@push('script')
<script>
(function () {
  const t = document.getElementById('adjT'), all = document.getElementById('allBtn'), cnt = document.getElementById('cnt');
  document.getElementById('showAll').addEventListener('change', e => t.classList.toggle('all', e.target.checked));
  const upd = () => {
    let n = 0;
    t.querySelectorAll('tbody tr').forEach(tr => {
      const inp = tr.querySelector('.act'), df = tr.querySelector('.df'), one = tr.querySelector('.one');
      const v = parseFloat((inp.value || '').replace(/,/g, ''));
      if (inp.value.trim() === '' || isNaN(v)) { df.textContent = '—'; df.style.color = ''; one.disabled = true; tr.classList.remove('chg'); return; }
      const d = Math.round((v - +tr.dataset.bal) * 100) / 100;
      df.textContent = d === 0 ? 'مظبوط' : (d > 0 ? '+' : '') + SG.fmt(d);
      df.style.color = d < 0 ? 'var(--down)' : d > 0 ? 'var(--up)' : 'var(--ink-3)';
      one.disabled = d === 0; tr.classList.toggle('chg', d !== 0); if (d !== 0) n++;
    });
    cnt.textContent = n; all.disabled = n === 0;
  };
  t.addEventListener('input', upd); upd();
  document.getElementById('adjF').addEventListener('submit', e => {
    const one = e.submitter && e.submitter.name === 'only';
    if (!confirm(one ? 'نضبطو الرصيد هذا؟' : 'نضبطو ' + cnt.textContent + ' رصيد؟')) e.preventDefault();
  });
})();
</script>
@endpush
