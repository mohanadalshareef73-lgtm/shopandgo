@extends('sg.layout')
@section('title', 'مشاكل الطلبات')
@section('bodyClass', 'soft')
@php use App\Models\OrderIssue as OI; use App\Services\TreasuryService as TS; $f = fn ($n) => TS::fmt($n); @endphp
@section('content')
<div class="crumb"><a href="{{ route('sg.orders.index') }}">الطلبات</a> › المشاكل</div>
<div class="top"><div><h1>مشاكل الطلبات</h1><div class="sub">النواقص، الطلبات التي لم تصل، التالفة، وأي مشكلة أخرى — حتى تُحل</div></div><div class="acts"><button class="sg-burger">☰</button></div></div>
<div class="stages">
    @foreach (['open' => 'مفتوحة', 'resolved' => 'محلولة', 'all' => 'الكل'] as $k => $v)
        <a href="{{ route('sg.issues', ['status' => $k, 'type' => request('type')]) }}" class="stg {{ $status === $k ? 'on' : '' }}">{{ $v }}<span class="n">{{ $k === 'all' ? $counts->sum() : ($counts[$k] ?? 0) }}</span></a>
    @endforeach
</div>
<div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px">
    <a class="pill {{ !request('type') ? 'on' : '' }}" href="{{ route('sg.issues', ['status' => $status]) }}">كل الأنواع</a>
    @foreach (OI::TYPES as $k => $t)<a class="pill {{ request('type') === $k ? 'on' : '' }}" href="{{ route('sg.issues', ['status' => $status, 'type' => $k]) }}">{{ $t[1] }} {{ $t[0] }}</a>@endforeach
</div>
<form method="post" action="{{ route('sg.issues.bulk') }}" id="isF">@csrf
<div class="card tw">
    @can('order-edit')
    <div class="tw-h" style="gap:10px">
        <label class="ck"><input type="checkbox" id="isAll"> تحديد الكل</label>
        <span class="note">محدد: <b class="num" id="isN">0</b></span>
        <span style="flex:1"></span>
        <button class="btn green sm" name="do" value="resolve" data-need>✓ تم الحل للمحددين <small>(بدون رسالة)</small></button>
        <button class="btn red sm" name="do" value="delete" data-need data-confirm-del>🗑 حذف المحددين</button>
    </div>
    @endcan
    <div class="scroll-x"><table class="t">
    <thead><tr>@can('order-edit')<th style="width:34px"></th>@endcan<th>الإيصال</th><th>العميل</th><th>المشكلة</th><th class="n">القيمة</th><th>الحالة</th><th>سجّلها</th><th></th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
        <tr>
            @can('order-edit')<td><input type="checkbox" class="isP" name="ids[]" value="{{ $r->id }}"></td>@endcan
            <td><a class="code" href="{{ route('sg.orders.show', $r->order_id) }}#issues">{{ optional($r->order)->code }}</a></td>
            <td class="cell2"><b>{{ optional($r->customer)->name }}</b><small class="num">{{ optional($r->customer)->code }}</small></td>
            <td>{{ OI::TYPES[$r->type][1] ?? '' }} {{ $r->typeLabel() }}@if ($r->qty) <span class="note">· {{ $r->qty }} قطعة</span>@endif @if ($r->note)<div class="note">{{ \Illuminate\Support\Str::limit($r->note, 80) }}</div>@endif</td>
            <td class="n num">{{ $r->value ? $f($r->value) : '—' }}</td>
            <td><span class="chip {{ $r->status === 'open' ? 'w' : 'u' }}">{{ $r->status === 'open' ? 'مفتوحة' : 'محلولة' }}</span>@if ($r->resolution)<div class="note">{{ \Illuminate\Support\Str::limit($r->resolution, 60) }}</div>@endif</td>
            <td class="note">{{ optional($r->creator)->name }} · {{ $r->created_at->diffForHumans() }}</td>
            <td><div style="display:flex;gap:6px;justify-content:flex-end;white-space:nowrap">
                @if ($r->status === 'open')
                    @can('order-edit')<button type="button" class="btn green sm" onclick="isOne({{ $r->id }}, 'resolve')" title="تتعلّم محلولة ومافيش أي رسالة للعميل">✓ تم الحل</button>
                    <a class="btn sm" href="{{ route('sg.orders.show', ['order' => $r->order_id, 'deduct' => $r->id]) }}#issues" title="يفتح الإيصال وقيمة النواقص جاهزة للخصم">💵 خصم</a>@endcan
                    <a class="btn ghost sm" href="{{ route('sg.orders.show', $r->order_id) }}#issues">فتح</a>
                @endif
                @can('order-edit')<button type="button" class="btn ghost sm" style="color:var(--down)" onclick="isOne({{ $r->id }}, 'delete')" title="حذف">🗑</button>@endcan
            </div></td>
        </tr>
    @empty
        <tr><td colspan="8"><div class="empty"><span class="ic">✓</span>لا توجد مشاكل هنا</div></td></tr>
    @endforelse
    </tbody>
</table></div></div>
</form>
<script>
(function () {
  const f = document.getElementById('isF'); if (!f) return;
  const picks = () => [...f.querySelectorAll('.isP')];
  const upd = () => { const n = picks().filter(p => p.checked).length; const el = document.getElementById('isN'); if (el) el.textContent = n; f.querySelectorAll('[data-need]').forEach(b => b.disabled = !n); };
  f.addEventListener('change', e => { if (e.target.id === 'isAll') picks().forEach(p => p.checked = e.target.checked); upd(); });
  f.querySelectorAll('[data-confirm-del]').forEach(b => b.addEventListener('click', e => { if (!confirm('نحذفو المشاكل المحددة نهائياً؟ (لو فيها خصم أو ترجيع، الفلوس ما ترجعش)')) e.preventDefault(); }));
  window.isOne = (id, act) => {
    if (act === 'delete' && !confirm('نحذفو المشكلة هذي نهائياً؟')) return;
    picks().forEach(p => p.checked = +p.value === id);
    const h = document.createElement('input'); h.type = 'hidden'; h.name = 'do'; h.value = act; f.appendChild(h); f.submit();
  };
  upd();
})();
</script>
@include('sg.partials.pager', ['p' => $rows])
@endsection
