{{-- ✅ أرقام حية + آخر تقييمات الزبائن (اللي وافقت عليها الإدارة) --}}
@php $st = \App\Services\ReviewService::stats(); $rv = \App\Services\ReviewService::latest(6); @endphp
<div style="margin:18px 18px 10px;background:#fff;border-radius:18px;padding:16px;text-align:center">
    <div style="font-size:13px;color:var(--ink-3)">سلّمنا لأصحابها</div>
    <div style="font-family:InterNum,Somar;font-weight:800;font-size:30px;color:var(--brand)">+{{ number_format($st['delivered']) }}</div>
    <div style="font-size:13px;color:var(--ink-2)">طلبية ✅ @if ($st['reviews']) · ⭐ {{ $st['rating'] }} من {{ $st['reviews'] }} تقييم @endif</div>
</div>
@if ($rv->count())
<div style="display:flex;gap:10px;overflow-x:auto;padding:4px 18px 16px;scroll-snap-type:x mandatory">
    @foreach ($rv as $r)
        <div style="flex:0 0 240px;scroll-snap-align:start;background:#fff;border-radius:16px;padding:12px 14px;font-size:13.5px;line-height:1.7">
            @if ($r->photo)<img src="{{ asset('storage/' . $r->photo) }}" alt="" loading="lazy" style="width:100%;height:130px;object-fit:cover;border-radius:10px;margin-bottom:8px">@endif
            <div style="color:#f5a623;font-size:15px">{{ str_repeat('★', $r->rating) }}<span style="color:#d9dce4">{{ str_repeat('★', 5 - $r->rating) }}</span></div>
            @if ($r->body)<div>{{ \Illuminate\Support\Str::limit($r->body, 140) }}</div>@endif
            <div style="color:var(--ink-3);font-size:12px;margin-top:4px">— {{ $r->publicName() }}</div>
        </div>
    @endforeach
</div>
<div style="text-align:center;margin-bottom:24px"><a href="{{ route('reviews.public') }}" style="color:var(--brand);font-weight:700;font-size:14px">كل التقييمات ←</a></div>
@endif
