@php
    $sourceMessage = $cart->incomingMessage;
    $sourceUuid = $sourceMessage?->session_uuid;
    $sourceName = $sourceMessage?->session_name ?? ($receivingSessions[$sourceUuid]['name'] ?? null);
    $sourcePhone = $sourceMessage?->receiving_phone;
    $compact = $compact ?? false;
@endphp
@if ($cart->origin !== 'manual')
    <div @class(['shein-receiving-source', 'is-compact' => $compact])>
        <span class="shein-source-label"><i class="ti ti-brand-whatsapp" aria-hidden="true"></i> رقم الاستقبال</span>
        @if ($sourcePhone)<bdi class="shein-source-phone">{{ $sourcePhone }}</bdi>
        @else <span class="text-muted">غير متاح</span>@endif
        @if ($sourceName)<bdi class="shein-source-session">{{ $sourceName }}</bdi>
        @elseif ($sourceUuid)<bdi class="shein-source-session">{{ $sourceUuid }}</bdi>
        @else <span class="text-muted">الجلسة غير محفوظة</span>@endif
        @if (! $compact && $sourceName && $sourceUuid)
            <details class="shein-source-details"><summary>معرّف جلسة الاستقبال</summary><bdi>{{ $sourceUuid }}</bdi></details>
        @endif
    </div>
@endif
