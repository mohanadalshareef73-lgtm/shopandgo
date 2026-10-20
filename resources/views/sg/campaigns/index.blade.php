@extends('sg.layout')
@section('title', 'حملات واتساب')
@section('bodyClass', 'soft')
@php use App\Services\CampaignService as CS; $sesName = collect($sessions)->pluck('name', 'uuid'); @endphp
@section('content')
<div class="top"><div><h1>📣 حملات واتساب</h1><div class="sub">رسالة لكل العملاء على دفعات: النشطين أول، بعدين المتوسطين، بعدين الخاملين — بشوية بشوية باش ما ياخذش الرقم باند</div></div>
    <div class="acts"><button class="sg-burger">☰</button></div></div>

<div class="hint mb">📊 اليوم تبعتت <b class="num">{{ $sentToday }}</b> رسالة من الرقم الأساسي من أصل <b class="num">{{ $limit }}</b> ·
    الحملة تقف وحدها لما يوصل <b class="num">{{ $limit - $reserve }}</b> باش يبقى <b class="num">{{ $reserve }}</b> للطلبيات والتنبيهات · من كل رقم رسالة كل دقيقة لدقيقتين ونص (عشوائي)</div>

<div class="card mb" style="padding:18px">
<h3 style="margin:0 0 10px">➕ حملة جديدة</h3>
<form method="post" action="{{ route('sg.campaigns.store') }}">@csrf
    <div class="f"><label class="l">اسم الحملة (لك انت بس)</label><input name="name" class="inp" value="{{ old('name', 'تعريف بالمنظومة') }}" required></div>
    <div class="f"><label class="l">الرسالة <span class="note">{الاسم} = اسم العميل · {كود_الزبون}</span></label><textarea name="message" class="inp" rows="9" required>{{ old('message', CS::DEFAULT_MESSAGE) }}</textarea></div>
    <div class="f"><label class="l">لمين؟ (بالترتيب)</label>
        <div style="display:flex;gap:10px;flex-wrap:wrap">@foreach (CS::SEGMENTS as $k => [$t, $d])
            <label class="ck" style="border:1px solid var(--line);border-radius:12px;padding:10px 14px;margin:0"><input type="checkbox" name="segments[]" value="{{ $k }}" checked> <b>{{ $t }}</b> <span class="num">({{ $counts[$k] }})</span><br><small class="note">{{ $d }}</small></label>
        @endforeach</div></div>
    <div class="f"><label class="l">نبعتو من أي رقم؟ <span class="note">لو اخترت رقمين، العملاء يتقسمو بيناتهم — كل عميل تجيه رسالة وحدة بس من رقم واحد</span></label>
        @if (!$sessions)<div class="note" style="color:var(--down)">ما قدرناش نجيبو أرقام Whatsy توا — لو ما اخترتش حتى رقم يبعت من الرقم الأساسي.</div>@endif
        <div style="display:flex;gap:10px;flex-wrap:wrap">@foreach ($sessions as $ss)
            <label class="ck" style="border:1px solid var(--line);border-radius:12px;padding:10px 14px;margin:0"><input type="checkbox" name="sessions[]" value="{{ $ss['uuid'] }}" @checked($ss['uuid'] === $main)> 📱 <b>{{ $ss['name'] }}</b> @if ($ss['uuid'] === $main)<span class="chip n" style="font-size:11px">الأساسي</span>@endif <span class="note">{{ $ss['status'] }}</span></label>
        @endforeach</div></div>
    <div class="grid g2 f" style="grid-template-columns:repeat(3,1fr)">
        <div><label class="l">كم رسالة في اليوم من كل رقم</label><input name="per_day" class="inp numr" value="{{ old('per_day', 120) }}" inputmode="numeric"><small class="note">ننصح 100–150 للبداية</small></div>
        <div><label class="l">من الساعة</label><input name="from_hour" class="inp numr" value="{{ old('from_hour', 11) }}"></div>
        <div><label class="l">لين الساعة</label><input name="to_hour" class="inp numr" value="{{ old('to_hour', 21) }}"></div>
    </div>
    <button class="btn green" data-confirm="نبدو الحملة؟ الرسايل تمشي على دفعات من اليوم">🚀 ابدا الحملة</button>
</form>
</div>

@foreach ($camps as $c)
    @php $st = collect($stats[$c->id] ?? []); $tot = $st->sum('n'); $sent = $st->where('status', 'sent')->sum('n'); $fail = $st->where('status', 'failed')->sum('n'); $pend = $st->where('status', 'pending')->sum('n'); @endphp
    <div class="card mb" style="padding:16px">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
            <h3 style="margin:0">{{ $c->name }}</h3>
            <span class="chip {{ $c->status === 'running' ? 'u' : ($c->status === 'done' ? 'n' : 'w') }}">{{ ['running' => '▶ شغالة', 'paused' => '⏸ موقوفة', 'done' => '✓ كملت'][$c->status] ?? $c->status }}</span>
            <span class="note">{{ $c->per_day }} في اليوم لكل رقم · من {{ $c->from_hour }} لين {{ $c->to_hour }}</span>
            <div style="margin-inline-start:auto;display:flex;gap:6px">
                @if ($c->status !== 'done')<form method="post" action="{{ route('sg.campaigns.toggle', $c->id) }}">@csrf<button class="btn ghost sm">{{ $c->status === 'running' ? '⏸ وقف' : '▶ كمّل' }}</button></form>@endif
                <form method="post" action="{{ route('sg.campaigns.destroy', $c->id) }}" data-confirm="نحذفو الحملة؟">@csrf @method('DELETE')<button class="btn ghost sm" style="color:var(--down)">🗑</button></form>
            </div>
        </div>
        <div style="height:10px;background:var(--line-soft);border-radius:99px;margin:12px 0 6px;overflow:hidden"><div style="height:100%;width:{{ $tot ? round($sent / $tot * 100) : 0 }}%;background:var(--up)"></div></div>
        <div class="note">تبعتت <b class="num">{{ $sent }}</b> من <b class="num">{{ $tot }}</b> · باقي <b class="num">{{ $pend }}</b>@if ($fail) · <span style="color:var(--down)">ما وصلتش {{ $fail }}</span>@endif</div>
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:8px;font-size:13px">
            @foreach (CS::SEGMENTS as $k => [$t])@php $g = $st->where('segment', $k); @endphp @if ($g->sum('n'))<span>{{ $t }}: <b class="num">{{ $g->where('status', 'sent')->sum('n') }}</b>/<span class="num">{{ $g->sum('n') }}</span></span>@endif @endforeach
            @foreach ($st->groupBy('session_uuid') as $ses => $g)<span class="note">📱 {{ $ses ? ($sesName[$ses] ?? substr($ses, 0, 6)) : 'الأساسي' }}: <b class="num">{{ $g->where('status', 'sent')->sum('n') }}</b>/<span class="num">{{ $g->sum('n') }}</span></span>@endforeach
        </div>
    </div>
@endforeach
@endsection
