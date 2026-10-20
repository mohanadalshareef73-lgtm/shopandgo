@extends('sg.layout')
@section('title', 'قائمة الحجز')
@push('style')
<style>
.bq { display: grid; gap: 8px; margin-top: 12px; }
.bq details { background: #fff; border-radius: 14px; border-right: 5px solid #e8590c; box-shadow: 0 2px 8px rgba(0,0,0,.04); }
.bq details.dn { border-right-color: #2f9e44; }
.bq summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: 10px; padding: 10px 14px; flex-wrap: wrap; }
.bq summary::-webkit-details-marker { display: none; }
.bq .cd { font-size: 19px; font-weight: 800; min-width: 64px; font-family: InterNum; }
.bq .who { flex: 1; min-width: 140px; font-size: 13.5px; }
.bq .rep { background: #fff4e6; color: #c2410c; border-radius: 99px; padding: 2px 9px; font-size: 12px; font-weight: 800; }
.bq .acts { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; }
.bq .more { padding: 0 14px 12px; }
.bq .items { background: #fff4e6; border-radius: 10px; padding: 8px 12px; white-space: pre-line; font-size: 13.5px; line-height: 1.8; }
.bq .open { font-size: 12px; color: #444; }
.bq details[open] .open { display: none; }
</style>
@endpush
@section('content')
<div class="top">
    <div><h1>📋 قائمة الحجز</h1><div class="sub">افتح السلة، احجزها، واضغط <b>✅ تم</b> · باقي <b>{{ $left }}</b> · اضغط على أي سطر تشوف التفاصيل</div></div>
    <div class="acts"><button class="sg-burger">☰</button></div>
</div>
<nav class="tabs">
    <a href="{{ route('sg.bqueue') }}" class="{{ $done ? '' : 'on' }}">⏳ تستنى الحجز <span class="b {{ $left ? 'r' : '' }}">{{ $left }}</span></a>
    <a href="{{ route('sg.bqueue', ['v' => 'done']) }}" class="{{ $done ? 'on' : '' }}">✅ تحجزت</a>
</nav>
<div class="bq">
@forelse ($rows as $ct)
    @php $code = optional($ct->customer)->code; $it = $items[$ct->id] ?? ''; $rep = str_contains($it, '🔁'); @endphp
    <details class="{{ $done ? 'dn' : '' }}">
        <summary>
            <span class="cd">{{ $code }}</span>
            <span class="who">{{ optional($ct->customer)->name }} · {{ $ct->retailer_label }} #{{ $ct->id }} @if ($rep)<a class="rep" target="_blank" href="{{ route('sg.pcarts.booking', $ct) }}" onclick="event.stopPropagation()">🔁 مكررة — افتح الـ PDF</a>@endif <span class="open">▾ عرض</span></span>
            <span class="acts" onclick="event.stopPropagation()">
                <a class="btn sm" target="_blank" href="{{ $ct->source_url }}">🔗 افتح</a>
                @if (!empty($ct->receipt_path))<a class="btn ghost sm" style="color:#1d4f8f" target="_blank" href="{{ route('sg.pcarts.receipt', $ct) }}">🧾 إيصال التحويل</a>@endif
                <button type="button" class="btn ghost sm" onclick="event.preventDefault();navigator.clipboard.writeText({{ json_encode((string) $ct->source_url) }});this.textContent='✓'">⧉ الرابط</button>
                <button type="button" class="btn ghost sm" onclick="event.preventDefault();navigator.clipboard.writeText({{ json_encode((string) $code) }});this.textContent='✓'">⧉ الكود</button>
                @if ($done)
                    <form method="post" action="{{ route('sg.bqueue.done', $ct) }}" style="display:inline">@csrf<input type="hidden" name="undo" value="1"><button class="btn ghost sm">↩ رجعها</button></form>
                @else
                    <form method="post" action="{{ route('sg.bqueue.done', $ct) }}" style="display:inline">@csrf<button class="btn sm" style="background:#2f9e44;color:#fff">✅ تم</button></form>
                @endif
            </span>
        </summary>
        <div class="more">
            <div class="note mb">{{ $ct->confirmed_at ? 'تأكدت ' . \Carbon\Carbon::parse($ct->confirmed_at)->format('m-d g:i a') : '' }}@if ($done) · ✅ تحجزت {{ $ct->booked_done_at ? \Carbon\Carbon::parse($ct->booked_done_at)->format('m-d g:i a') : '' }} · {{ $users[$ct->booked_by] ?? '' }}@endif</div>
            <a class="btn sm mb" style="background:#e8590c;color:#fff" target="_blank" href="{{ route('sg.pcarts.booking', $ct) }}">📄 افتح الـ PDF (المكرر ملوّن)</a>
            <a class="btn ghost sm mb" href="{{ route('sg.pcarts.download', $ct) }}">⬇ تحميل</a>
        </div>
    </details>
@empty
    <div class="card" style="padding:30px;text-align:center;font-size:16px">{{ $done ? 'ما فيش' : '🎉 ما فيش سلات تستنى الحجز' }}</div>
@endforelse
</div>
@endsection
