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
<div class="card tw"><div class="scroll-x"><table class="t">
    <thead><tr><th>الإيصال</th><th>العميل</th><th>المشكلة</th><th class="n">القيمة</th><th>الحالة</th><th>سجّلها</th><th></th></tr></thead>
    <tbody>
    @forelse ($rows as $r)
        <tr>
            <td><a class="code" href="{{ route('sg.orders.show', $r->order_id) }}#issues">{{ optional($r->order)->code }}</a></td>
            <td class="cell2"><b>{{ optional($r->customer)->name }}</b><small class="num">{{ optional($r->customer)->code }}</small></td>
            <td>{{ OI::TYPES[$r->type][1] ?? '' }} {{ $r->typeLabel() }}@if ($r->qty) <span class="note">· {{ $r->qty }} قطعة</span>@endif @if ($r->note)<div class="note">{{ \Illuminate\Support\Str::limit($r->note, 80) }}</div>@endif</td>
            <td class="n num">{{ $r->value ? $f($r->value) : '—' }}</td>
            <td><span class="chip {{ $r->status === 'open' ? 'w' : 'u' }}">{{ $r->status === 'open' ? 'مفتوحة' : 'محلولة' }}</span>@if ($r->resolution)<div class="note">{{ \Illuminate\Support\Str::limit($r->resolution, 60) }}</div>@endif</td>
            <td class="note">{{ optional($r->creator)->name }} · {{ $r->created_at->diffForHumans() }}</td>
            <td>@if ($r->status === 'open')<a class="btn ghost sm" href="{{ route('sg.orders.show', $r->order_id) }}#issues">حل</a>@endif</td>
        </tr>
    @empty
        <tr><td colspan="7"><div class="empty"><span class="ic">✓</span>لا توجد مشاكل هنا</div></td></tr>
    @endforelse
    </tbody>
</table></div></div>
@include('sg.partials.pager', ['p' => $rows])
@endsection
