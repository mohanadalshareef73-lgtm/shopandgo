@extends('sg.layout')
@section('title', 'بلاغات العملاء')
@section('bodyClass', 'soft')
@php use App\Models\SupportTicket as T; @endphp
@section('content')
<div class="top"><div><h1>بلاغات العملاء</h1><div class="sub">المشكلات التي يرسلها العملاء من حساباتهم، والرد عليها</div></div><div class="acts"><button class="sg-burger">☰</button></div></div>
<div class="stages">
    @foreach (['open' => 'جديدة', 'answered' => 'تم الرد', 'closed' => 'تم الحل', 'all' => 'الكل'] as $k => $v)
        <a href="{{ route('sg.tickets', ['status' => $k]) }}" class="stg {{ $status === $k ? 'on' : '' }}">{{ $v }}<span class="n">{{ $k === 'all' ? $counts->sum() : ($counts[$k] ?? 0) }}</span></a>
    @endforeach
</div>
@forelse ($rows as $t)
    <div class="card mb">
        <div style="display:flex;gap:12px;align-items:flex-start;flex-wrap:wrap">
            <span class="av" style="width:40px;height:40px">{{ mb_substr(optional($t->customer)->name, 0, 1) }}</span>
            <div style="flex:1;min-width:260px">
                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap"><b>{{ optional($t->customer)->name }}</b><span class="note num">{{ optional($t->customer)->code }} · {{ optional($t->customer)->phone_1 }}</span><span class="chip n">{{ T::TYPES[$t->type] ?? $t->type }}</span>@if ($t->order)<a class="code" href="{{ route('sg.orders.show', $t->order) }}">{{ $t->order->code }}</a>@endif<span class="chip {{ T::STATUSES[$t->status][1] ?? 'n' }}">{{ T::STATUSES[$t->status][0] ?? $t->status }}</span><span class="note">{{ $t->created_at->diffForHumans() }}</span>@can('customer-edit')<form method="post" action="{{ route('sg.tickets.destroy', $t) }}" style="margin-inline-start:auto">@csrf @method('DELETE')<button class="btn ghost sm" style="color:var(--down)" title="مسح البلاغ (ما يوصل حتى شي للعميل)" data-confirm="نمسحو البلاغ هذا؟ العميل ما يوصله حتى شي">✕</button></form>@endcan</div>
                <p style="margin:8px 0;line-height:1.8">{{ $t->message }}</p>
                @if ($t->image)<a href="{{ asset('storage/' . $t->image) }}" target="_blank"><img src="{{ asset('storage/' . $t->image) }}" style="max-height:120px;border-radius:10px"></a>@endif
                @if ($t->reply)<div class="hint" style="margin-top:8px">💬 {{ $t->reply }} <span class="note">— {{ optional($t->replier)->name }} · {{ optional($t->replied_at)->format('Y-m-d g:i a') }}</span></div>@endif
            </div>
            @if ($t->status !== 'closed')
            <form method="post" action="{{ route('sg.tickets.reply', $t) }}" style="width:360px;max-width:100%">
                @csrf
                <textarea name="reply" class="inp" rows="3" placeholder="اكتب الرد للعميل…" required style="height:auto"></textarea>
                <label class="ck mt"><input type="checkbox" name="send_whatsapp" value="1" checked> إرسال الرد عبر واتساب أيضاً</label>
                <div style="display:flex;gap:8px;margin-top:8px"><button class="btn sm">إرسال الرد</button><button class="btn green sm" name="close" value="1">إرسال وإغلاق البلاغ</button></div>
            </form>
            @endif
        </div>
    </div>
@empty
    <div class="card"><div class="empty"><span class="ic">✓</span>لا توجد بلاغات</div></div>
@endforelse
@include('sg.partials.pager', ['p' => $rows])
@endsection
