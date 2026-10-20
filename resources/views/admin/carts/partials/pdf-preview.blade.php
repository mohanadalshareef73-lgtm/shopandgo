<dialog id="quotation-pdf-preview" class="shein-pdf-preview" aria-labelledby="quotation-pdf-title">
    <header class="shein-pdf-toolbar">
        <h5 id="quotation-pdf-title" class="mb-0">عرض السعر #{{ $cart->id }}</h5>
        <div class="shein-pdf-actions">
            <a href="{{ route('carts.quotation', $cart) }}" class="btn btn-primary"><i class="ti ti-download" aria-hidden="true"></i> تحميل PDF</a>
            <button type="button" class="btn btn-outline-secondary" data-pdf-close aria-label="إغلاق عرض PDF" autofocus><i class="ti ti-x" aria-hidden="true"></i> إغلاق</button>
        </div>
    </header>
    <iframe class="shein-pdf-frame" title="عرض سعر PDF للسلة #{{ $cart->id }}" data-pdf-src="{{ route('carts.quotation', ['cart' => $cart, 'preview' => 1]) }}"></iframe>
    <footer class="shein-pdf-footer">
        <a href="{{ route('carts.quotation', ['cart' => $cart, 'preview' => 1]) }}" target="_blank" rel="noopener noreferrer">إذا لم يظهر الملف، افتحه في نافذة جديدة <i class="ti ti-external-link" aria-hidden="true"></i></a>
    </footer>
</dialog>
