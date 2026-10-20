<div class="card" id="status-history">
    <div class="card-header"><h5 class="mb-0">سجل تغييرات الحالة</h5></div>
    <div class="card-body">
        <p class="text-muted small">التاريخ والوقت حسب {{ config('app.timezone') }}</p>
        @forelse ($statusAudits as $audit)
            <div class="border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between gap-2 mb-2">
                    <strong>{{ $audit->actor_name }}</strong>
                    <span class="badge bg-light-{{ $audit->actor_type === 'customer' ? 'success' : 'primary' }}">
                        {{ $audit->actor_type === 'customer' ? 'العميل عبر واتساب' : 'موظف النظام' }}
                    </span>
                </div>
                @if ($audit->actor_phone)<div class="text-muted small" dir="ltr">{{ $audit->actor_phone }}</div>@endif
                <time class="d-block text-muted small mb-2" dir="ltr" datetime="{{ $audit->changed_at->toIso8601String() }}">
                    {{ $audit->changed_at->format('Y-m-d H:i:s') }}
                </time>
                @if ($audit->previous_status !== $audit->new_status)
                    <p class="mb-1">من <strong>{{ \App\Models\CartQuote::OPERATOR_STATUSES[$audit->previous_status] ?? $audit->previous_status }}</strong>
                        إلى <strong>{{ \App\Models\CartQuote::OPERATOR_STATUSES[$audit->new_status] ?? $audit->new_status }}</strong></p>
                @endif
                @if ($audit->previous_customer_status !== $audit->new_customer_status)
                    <p class="text-muted small mb-1">قرار العميل: {{ \App\Models\CartQuote::CUSTOMER_STATUSES[$audit->previous_customer_status] ?? $audit->previous_customer_status }}
                        ← {{ \App\Models\CartQuote::CUSTOMER_STATUSES[$audit->new_customer_status] ?? $audit->new_customer_status }}</p>
                @endif
                @if ($audit->previous_notes !== $audit->new_notes)
                    <details class="mt-2">
                        <summary>تم تعديل ملاحظات المتابعة</summary>
                        <p class="text-muted small mt-2 mb-1">قبل: {{ $audit->previous_notes ?: 'بدون ملاحظات' }}</p>
                        <p class="small mb-0">بعد: {{ $audit->new_notes ?: 'بدون ملاحظات' }}</p>
                    </details>
                @endif
            </div>
        @empty
            <p class="text-muted mb-0">لا توجد تغييرات مسجلة بعد.</p>
        @endforelse
        {{ $statusAudits->fragment('status-history')->links('pagination::bootstrap-5') }}
    </div>
</div>
