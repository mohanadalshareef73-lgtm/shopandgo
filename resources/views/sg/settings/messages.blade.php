@extends('sg.layout')
@section('title', 'رسائل الواتساب')
@push('style')
<style>
.tl a { display: flex; gap: 10px; align-items: center; padding: 10px 12px; border-radius: 12px; }
.tl a.on { background: var(--line-soft); }
.tl a small { display: block; font-size: 12px; color: var(--ink-3); }
.vars { display: flex; flex-wrap: wrap; gap: 6px; margin: 10px 0; }
.vars button { border: 1px solid var(--line); background: #fff; border-radius: 8px; padding: 4px 8px; font: inherit; font-size: 12.5px; cursor: pointer; }
.phone { background: #e5ddd5; border-radius: 16px; padding: 14px; min-height: 160px; }
.bubble { background: #dcf8c6; border-radius: 10px 0 10px 10px; padding: 9px 12px; max-width: 92%; margin-inline-start: auto; white-space: pre-wrap; font-size: 14px; line-height: 1.7; box-shadow: 0 1px 1px rgba(0,0,0,.08); }
</style>
@endpush
@section('content')
@php $f = fn ($n) => number_format($n); @endphp
<div class="top">
    <div><h1>رسائل الواتساب</h1><div class="sub">كل رسالة تُرسل أوتوماتيك: شغّلها أو وقّفها، وعدّل كلامها</div></div>
    <div class="acts">@can('aiRoles-list')<a class="btn ghost" href="{{ route('ai-roles.index') }}">🤖 قواعد الرد الآلي</a>@endcan<a class="btn ghost" href="{{ route('settings.whatsapp.edit') }}">⚙ ربط الواتساب</a><button class="sg-burger">☰</button></div>
</div>
<div class="kpis g4">
    <div class="card kpi accent"><div class="lbl">أُرسلت آخر 30 يوم</div><div class="v num">{{ $f($sent) }}</div></div>
    <div class="card kpi {{ $failed ? 'red' : '' }}"><div class="lbl">لم تصل</div><div class="v num">{{ $f($failed) }}</div><div class="s">{{ $sent + $failed ? round($failed / ($sent + $failed) * 100, 1) : 0 }}%</div></div>
    <div class="card kpi"><div class="lbl">رسائل شغالة</div><div class="v num">{{ $tpls->filter(fn ($t) => $t->enabled ?? true)->count() }} <small>من {{ $tpls->count() }}</small></div></div>
    <div class="card kpi"><div class="lbl">أكثر رسالة</div><div class="v" style="font-size:18px">{{ optional($stats->sortByDesc('n')->first())->type ?: '—' }}</div></div>
</div>
<div class="side-lay l">
    <div class="card tl" style="padding:8px">
        @foreach ($tpls as $t)
            @php $ttl = $titles[$t->type] ?? [$t->title ?: $t->type, '']; @endphp
            <a href="?id={{ $t->id }}" class="{{ $sel && $sel->id === $t->id ? 'on' : '' }}"><span style="font-size:18px">{{ ($t->enabled ?? true) ? '🟢' : '⚪' }}</span><div style="flex:1"><b>{{ $t->title ?: $ttl[0] }}</b><small>{{ $ttl[1] ?: $t->type }}</small></div><span class="note num">{{ (int) $stats->where('type', $t->type)->where('status', 'sent')->sum('n') }}</span></a>
        @endforeach
    </div>
    @if ($sel)
    <div>
        <div class="grid g2 mb">
            <form class="card" method="post" action="{{ route('sg.messages.update', $sel) }}">
                @csrf @method('PUT')
                <h3>{{ $sel->title ?: ($titles[$sel->type][0] ?? $sel->type) }} <label class="tgl"><input type="checkbox" name="enabled" value="1" @checked($sel->enabled ?? true)><span class="tg"></span><span class="note">شغالة</span></label></h3>
                <div class="f"><label class="l">العنوان</label><input name="title" class="inp" value="{{ $sel->title }}"></div>
                <label class="l">نص الرسالة</label>
                <textarea name="message" id="msg" class="inp" rows="11">{{ $sel->message }}</textarea>
                <div class="note mt">اضغط على خانة لإدراجها في مكان المؤشر:</div>
                <div class="vars">@foreach ($vars as $k => $v)<button type="button" data-v="{{ $k }}" title="{{ $v }}">{{ $v }}</button>@endforeach</div>
                <button class="btn block">حفظ</button>
            </form>
            <div class="card">
                <h3>كيف تظهر للعميل <span class="h-note">ببيانات {{ optional(optional($sample)->customer)->name ?: 'تجربة' }}</span></h3>
                <div class="phone"><div class="bubble" id="prev"></div></div>
                <form method="post" action="{{ route('sg.messages.test', $sel) }}" style="display:flex;gap:6px;margin-top:12px">@csrf<input name="phone" class="inp numr" placeholder="رقمك للتجربة 09…" required><button class="btn wa">💬 جرّب</button></form>
            </div>
        </div>
        <div class="card tw">
            <div class="tw-h"><h3>آخر الرسائل المبعوتة</h3></div>
            <div class="scroll-x">
            <table class="t"><thead><tr><th>الوقت</th><th>النوع</th><th>العميل</th><th>الرقم</th><th>الحالة</th></tr></thead><tbody>
            @forelse ($recent as $r)
                <tr><td class="note num">{{ $r->created_at->format('j/n g:i') }}</td><td>{{ $titles[$r->type][0] ?? $r->type }}</td><td>{{ optional($r->customer)->name }}</td><td class="num">{{ $r->phone }}</td><td>@if ($r->status === 'sent')<span class="chip u">وصلت</span>@else<span class="chip d" title="{{ $r->error }}">لم تصل</span>@endif</td></tr>
            @empty
                <tr><td colspan="5"><div class="empty">لا يوجد</div></td></tr>
            @endforelse
            </tbody></table>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
@push('script')
<script>
(function () {
  const m = document.getElementById('msg'); if (!m) return;
  const vars = @json($sample ? \App\Services\MessageService::orderVars($sample) : []);
  const prev = () => { let t = m.value; Object.keys(vars).forEach(k => { t = t.split(k).join(vars[k] ?? ''); }); document.getElementById('prev').textContent = t.replace(/\*([^*]+)\*/g, '$1'); };
  document.querySelectorAll('.vars button').forEach(b => b.onclick = () => { const s = m.selectionStart, v = b.dataset.v; m.value = m.value.slice(0, s) + v + m.value.slice(m.selectionEnd); m.focus(); m.selectionStart = m.selectionEnd = s + v.length; prev(); });
  m.addEventListener('input', prev); prev();
})();
</script>
@endpush
