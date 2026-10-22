@extends('sg.layout')
@section('title', 'تقييمات الشركة')
@section('bodyClass', 'soft')
@section('content')
<div class="top">
    <div><h1>⭐ تقييمات الشركة</h1><div class="sub">الزبون يقيّم بعد ما يستلم، وانت توافق قبل ما يطلع للناس</div></div>
    <div class="acts"><a class="btn ghost" href="{{ route('reviews.public') }}" target="_blank">صفحة التقييمات العامة ↗</a><button class="sg-burger">☰</button></div>
</div>
@if (!$has)<div class="flash err">لازم تفتح /system-update مرة وحدة باش تخدم صفحة التقييمات</div>@endif
<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">سلّمنا لأصحابها</div><div class="v num">{{ number_format($stats['delivered']) }}</div><div class="s">الرقم الحي اللي يطلع للناس</div></div>
    <div class="card kpi"><div class="lbl">متوسط التقييم</div><div class="v num">{{ $stats['rating'] ?: '—' }} <small>/ 5</small></div><div class="s">من {{ $stats['reviews'] }} تقييم منشور</div></div>
    <div class="card kpi {{ ($counts['pending'] ?? 0) ? 'red' : '' }}"><div class="lbl">تستنى موافقتك</div><div class="v num">{{ $counts['pending'] ?? 0 }}</div><div class="s">جديدة</div></div>
    <div class="card kpi"><div class="lbl">زبائن</div><div class="v num">{{ number_format($stats['customers']) }}</div><div class="s">مسجلين في المنظومة</div></div>
</div>
<div class="stages">
    @foreach (['pending' => 'تستنى موافقة', 'approved' => 'منشورة', 'rejected' => 'مرفوضة'] as $k => $v)
        <a href="{{ route('sg.reviews', ['tab' => $k]) }}" class="stg {{ $tab === $k ? 'on' : '' }}">{{ $v }}<span class="n">{{ $counts[$k] ?? 0 }}</span></a>
    @endforeach
</div>
<div class="grid g3">
    @forelse ($rows as $r)
        <div class="card" style="display:flex;flex-direction:column;gap:8px">
            @if ($r->photo)<a href="{{ asset('storage/' . $r->photo) }}" target="_blank"><img src="{{ asset('storage/' . $r->photo) }}" alt="" loading="lazy" style="width:100%;height:190px;object-fit:cover;border-radius:12px"></a>@endif
            <div style="display:flex;justify-content:space-between;align-items:center"><span style="color:#f5a623;font-size:18px">{{ str_repeat('★', $r->rating) }}<span style="color:#d9dce4">{{ str_repeat('★', 5 - $r->rating) }}</span></span><span class="note">{{ $r->created_at->diffForHumans() }}</span></div>
            <div style="font-size:15px;line-height:1.8">{{ $r->body ?: '— بدون كلام —' }}</div>
            <div class="note"><b style="color:#111">{{ optional($r->customer)->name }}</b> · <span class="num">{{ optional($r->customer)->code }}</span> · <span class="num">{{ optional($r->customer)->phone_1 }}</span>@if ($r->order) · <a class="num" href="{{ route('sg.orders.show', $r->order_id) }}">{{ $r->order->code }}</a>@endif</div>
            <div class="note">يطلع للناس باسم: <b>{{ $r->publicName() }}</b>@if ($r->decider) · قرر: {{ $r->decider->name }}@endif</div>
            <form method="post" action="{{ route('sg.reviews.act', $r) }}" style="display:flex;gap:6px;flex-wrap:wrap;margin-top:auto">@csrf
                @if ($r->status !== 'approved')<button name="do" value="approve" class="btn green sm" style="flex:1">✓ موافقة ونشر</button>@endif
                @if ($r->status !== 'rejected')<button name="do" value="reject" class="btn ghost sm">✕ رفض</button>@endif
                @if ($r->status !== 'pending')<button name="do" value="pending" class="btn ghost sm">↩ للمراجعة</button>@endif
                <button name="do" value="delete" class="btn red sm" onclick="return confirm('نحذفو التقييم نهائياً؟')">🗑</button>
            </form>
        </div>
    @empty
        <div class="card" style="grid-column:1/-1"><div class="empty"><span class="ic">⭐</span>ما فيش تقييمات هنا</div></div>
    @endforelse
</div>
@if ($has)@include('sg.partials.pager', ['p' => $rows])@endif
@endsection
