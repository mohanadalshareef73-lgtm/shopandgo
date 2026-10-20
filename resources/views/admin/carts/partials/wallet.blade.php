<div class="card mb-4" id="shein-wallet" data-url="{{ route('carts.wallet', [], false) }}"
    data-pause-on-empty-wallet="{{ $walletPauseEnabled ? '1' : '0' }}" aria-busy="true">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <h5 class="mb-0">محفظة مزود شي إن</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" id="wallet-refresh" disabled>
                <i class="ti ti-refresh me-1" aria-hidden="true"></i>تحديث الرصيد
            </button>
        </div>
        <div class="shein-wallet-grid">
            <div class="shein-wallet-value shein-wallet-available"><small>الرصيد المتاح</small><bdi data-wallet-amount="available">—</bdi></div>
            <div class="shein-wallet-value"><small>الرصيد المحجوز</small><bdi data-wallet-amount="held">—</bdi></div>
            <div class="shein-wallet-value"><small>الرصيد الإجمالي</small><bdi data-wallet-amount="balance">—</bdi></div>
        </div>
        <p class="small text-muted mt-3 mb-0" id="wallet-status" role="status" aria-live="polite">جارٍ تحديث رصيد المحفظة…</p>
        <p class="small text-warning mt-2 mb-0" id="wallet-pause-status" role="status" aria-live="polite" hidden></p>
        <noscript><p class="text-danger mt-2 mb-0">فعّل JavaScript لعرض رصيد المحفظة.</p></noscript>
    </div>
</div>
