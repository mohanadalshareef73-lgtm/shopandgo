<div class="card" id="pdf-editor">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">تعديل PDF فقط</h5>
        <span class="badge bg-light-primary">الختم: {{ $cart->pdf_stamp }}</span>
    </div>
    <div class="card-body">
        <p class="text-muted">عدّل الكميات والأسعار لهذا الملف فقط. بيانات المزود والعرض المحفوظ وإعدادات النظام تبقى كما هي.
            يتبع الختم حالة متابعة السلة. لا ترسل هذه التعديلات رسالة واتساب تلقائياً.</p>
        <div class="alert alert-light-primary">
            الإجمالي الحالي للملف: <strong dir="ltr">{{ $pdfQuotation['total']['amount'] }} LYD</strong>
            · عدد القطع: <strong>{{ $pdfQuotation['item_count'] }}</strong>
        </div>
        <form method="post" action="{{ route('carts.pdf.update', $cart) }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="revision" value="{{ old('revision', $pdfRevision) }}">
            <div class="row">
                <div class="col-md-6 form-group">
                    <label for="pdf_exchange_rate" class="form-label">سعر الصرف لهذا PDF <span dir="ltr">LYD / USD</span></label>
                    <input type="number" id="pdf_exchange_rate" name="exchange_rate" class="form-control" min="0.000001" max="999999.999999" step="0.000001" dir="ltr"
                        value="{{ old('exchange_rate', $pdfEditor['exchange_rate']) }}" required>
                </div>
                <div class="col-md-6 form-group">
                    <label for="pdf_company_percentage" class="form-label">نسبة الشركة لهذا PDF (%)</label>
                    <input type="number" id="pdf_company_percentage" name="company_percentage" class="form-control" min="0" max="1000" step="0.0001" dir="ltr"
                        value="{{ old('company_percentage', $pdfEditor['company_percentage']) }}" required>
                </div>
            </div>
            <p class="small text-muted">الأسعار الأساسية قبل النسبة. السعر النهائي اليدوي بالدولار اختياري ويستبدل الحساب الآلي للقطعة؛
                اتركه فارغاً لحساب السعر الأساسي × (1 + النسبة). تحوّل الأسعار إلى الدينار بسعر صرف هذا الملف.
                النسبة لا تظهر في PDF العميل.</p>
            <div class="table-responsive">
                <table class="table align-middle" style="table-layout:fixed;min-width:780px">
                    <thead><tr><th style="width:34%">المنتج</th><th style="width:9%">الكمية</th><th style="width:19%;white-space:normal">الأصلي الأساسي USD</th><th style="width:19%;white-space:normal">التخفيض الأساسي USD</th><th style="width:19%;white-space:normal">النهائي اليدوي USD</th></tr></thead>
                    <tbody>
                    @foreach ($cart->quotation['items'] as $index => $item)
                        <tr @class(['item-unavailable' => $item['availability'] === 'unavailable'])>
                            <td style="white-space:normal;overflow-wrap:anywhere">{{ $item['name'] }}<small class="d-block text-muted">{{ $item['variant'] }}</small>
                                @if ($item['availability'] !== 'available')<small class="d-block text-danger">غير متوفر - لا يدخل في الإجمالي</small>@endif
                            </td>
                            <td><input type="number" name="items[{{ $index }}][qty]" class="form-control" aria-label="كمية المنتج {{ $index + 1 }}"
                                min="1" max="100000" step="1" value="{{ old('items.'.$index.'.qty', $pdfEditor['items'][$index]['qty']) }}" required></td>
                            @foreach (['original_usd' => 'الأصلي الأساسي', 'sale_usd' => 'التخفيض الأساسي', 'customer_usd' => 'النهائي اليدوي'] as $field => $label)
                                <td><input type="number" name="items[{{ $index }}][{{ $field }}]" class="form-control" dir="ltr"
                                    aria-label="{{ $label }} للمنتج {{ $index + 1 }}" min="0" step="any"
                                    value="{{ old('items.'.$index.'.'.$field, $pdfEditor['items'][$index][$field]) }}"
                                    @if ($item['availability'] !== 'available') disabled @elseif ($field === 'sale_usd') required @endif
                                    @if ($field === 'customer_usd') placeholder="حساب آلي" @endif></td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <button type="submit" class="btn btn-primary">حفظ وإنشاء PDF</button>
            <a href="{{ route('carts.quotation', $cart) }}" class="btn btn-outline-primary">تحميل PDF الحالي</a>
        </form>
        @if ($cart->pdf_overrides)
            <form class="mt-3" method="post" action="{{ route('carts.pdf.reset', $cart) }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="revision" value="{{ $pdfRevision }}">
                <button type="submit" class="btn btn-outline-secondary">استعادة بيانات العرض المحفوظة</button>
            </form>
        @endif
    </div>
</div>
