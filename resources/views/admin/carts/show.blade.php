@extends('sg.layout')
@php $nav = $nav ?? 'cart'; @endphp

@php
    $isAmazon = $cart->retailer === 'amazon';
@endphp

@section('title'){{ $isAmazon ? 'متابعة منتج أمازون' : 'متابعة سلة '.$cart->retailer_label }} #{{ $cart->id }}@endsection

@push('style')
<link rel="stylesheet" href="{{ asset('assets/sg/compat.css') }}?v=1">
    @include('admin.carts.partials.page-styles')
@endpush

@section('content')
<div class="legacy">
<div class="top" style="justify-content:flex-end;margin-bottom:0"><div class="acts"><button class="sg-burger">☰</button></div></div>
    <section class="pc-container shein-page">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('carts.index') }}">السلات</a></li>
                        <li class="breadcrumb-item" aria-current="page">{{ $isAmazon ? 'منتج أمازون' : 'السلة' }} #{{ $cart->id }}</li>
                    </ul>
                    <div class="shein-heading">
                        <div><div class="shein-eyebrow"><span class="shein-wordmark" dir="ltr">{{ $cart->retailer_name }}</span><span>{{ $isAmazon ? 'تفاصيل المنتج' : 'تفاصيل السلة' }}</span></div><h2>{{ $isAmazon ? 'منتج أمازون' : 'السلة' }} #{{ $cart->id }}</h2>
                            <p>{{ $cart->incomingMessage?->name ?? 'عميل غير محدد' }} · {{ $cart->created_at->format('Y-m-d H:i') }}</p></div>
                        <div class="shein-heading-actions">
                            <a href="{{ route('carts.index') }}" class="btn btn-outline-secondary">كل السلات</a>
                            <a href="#cart-follow-up" class="btn btn-outline-secondary">متابعة العميل</a>
                            @if ($cart->pdf_path)<button type="button" class="btn btn-primary" data-pdf-open aria-haspopup="dialog" aria-controls="quotation-pdf-preview"><i class="ti ti-eye" aria-hidden="true"></i> عرض PDF</button>@endif
                        </div>
                    </div>
                </div>
            </div>

            @include('layout.validation-messages')

            <div class="shein-detail-layout">
                <div class="shein-detail-main">
                    <section class="card shein-quote-summary" aria-label="ملخص عرض السعر">
                        <div class="card-body">
                            <div class="shein-quote-heading"><div><span class="shein-summary-label">إجمالي عرض السعر</span>
                                @if ($cart->status === 'succeeded')<div class="shein-quote-total" dir="ltr">{{ data_get($customerTotal, 'amount') }} <small>{{ data_get($customerTotal, 'currency') }}</small></div>
                                @else <h3 class="mt-2">{{ $cart->status === 'failed' ? 'تعذر تجهيز العرض' : 'العرض قيد التجهيز' }}</h3>@endif
                            </div><div class="shein-quote-state">@include('admin.carts.partials.operator-status')<div class="mt-2">@include('admin.carts.partials.quote-status')</div></div></div>
                            <div class="shein-quote-facts">
                                <div><span>عدد القطع</span><strong>{{ $customerItemCount }}</strong></div>
                                <div><span>عدد المنتجات</span><strong>{{ count(data_get($cart->payload, 'result.items', [])) }}</strong></div>
                                @if ($cart->quotation)<div><span>سعر الصرف المحفوظ</span><strong dir="ltr">{{ data_get($priceDisplay, 'exchange_rate') }} LYD/USD</strong></div>@endif
                            </div>
                            @if ($quotationError ?? null)<div class="alert alert-warning mt-3 mb-0">تعذر تجهيز PDF: {{ $quotationError }}</div>@endif
                            @if ($cart->last_error)<div class="alert alert-warning mt-3 mb-0">ملاحظة تجهيز العرض: {{ $cart->last_error }}</div>@endif
                            <div class="shein-quote-links">
                                @if ($cart->quotation)@can('cart-edit')<a href="#pdf-editor"><i class="ti ti-edit" aria-hidden="true"></i> تعديل عرض السعر</a>@endcan @endif
                                @if (in_array(strtolower(parse_url($cart->source_url, PHP_URL_SCHEME) ?? ''), ['http', 'https']))<a href="{{ $cart->source_url }}" target="_blank" rel="noopener noreferrer"><i class="ti ti-external-link" aria-hidden="true"></i> {{ $isAmazon ? 'فتح المنتج في أمازون' : 'فتح الرابط في '.$cart->retailer_label }}</a>@endif
                                @if (in_array($cart->status, ['pending', 'submitted']))<a href="{{ route('carts.show', $cart) }}">تحديث العرض</a>@endif
                            </div>
                        </div>
                    </section>
                    @if($isAmazon)@include('admin.carts.partials.amazon-pricing')@endif
                    <section class="card shein-products-panel" aria-labelledby="cart-products-title">
                        <div class="card-header shein-section-heading"><h5 id="cart-products-title" class="mb-0">{{ $isAmazon ? 'تفاصيل منتج أمازون' : 'منتجات السلة' }}</h5><span class="text-muted small">اضغط على الصورة لتكبيرها</span></div>
                        <div class="shein-product-list">
                            @forelse (data_get($cart->payload, 'result.items', []) as $index => $item)
                                @php
                                    $quotedItem = data_get($pdfQuotation, 'items.'.$index);
                                    $line = $item['outputLine'] ?? $item['line'] ?? null;
                                    $unavailable = ($item['availability'] ?? null) === 'unavailable';
                                @endphp
                                <article @class(['shein-product-row', 'is-unavailable' => $unavailable])>
                                    <button class="shein-product-photo" type="button" data-image-open aria-label="تكبير صورة {{ $item['name'] ?? 'المنتج' }}" @disabled(empty($item['imageUrl']))>
                                        @include('admin.carts.partials.product-image', ['imageCart' => $cart, 'imageItem' => $item, 'imageIndex' => $index])
                                    </button>
                                    <div class="shein-product-info">
                                        <span class="shein-product-position">المنتج {{ $loop->iteration }}</span>
                                        <h6>{{ data_get($quotedItem, 'name', $item['name'] ?? 'منتج') }}</h6>
                                        <p class="shein-product-variant">{{ data_get($quotedItem, 'variant', $item['variant'] ?? '') }}</p>
                                        @if($isAmazon)
                                            @php($offer = \App\Support\AmazonOfferDetails::fromItem($item))
                                            @if($offer['notice'])<div class="alert alert-warning mt-2 mb-2" dir="auto">{{ $offer['notice'] }}</div>@endif
                                            @if($offer['seller'])<p class="small mb-1" dir="auto">{{ $offer['seller'] }}</p>@endif
                                            @if($offer['delivery'])<p class="small mb-1"><bdi>{{ $offer['delivery'] }}</bdi><br>{{ \App\Support\AmazonOfferDetails::DELIVERY_EXCLUSION }}</p>@endif
                                        @endif
                                        <div class="shein-product-meta"><span>الكمية <strong>{{ data_get($quotedItem, 'qty', $item['qty'] ?? '—') }}</strong></span>
                                            <span @class(['shein-availability', 'is-unavailable' => $unavailable])>{{ $unavailable ? 'غير متوفر' : (($item['availability'] ?? null) === 'available' ? 'متوفر' : 'التوفر غير محدد') }}</span>
                                            @if (($item['qtySource'] ?? null) === 'assumed_one')<span class="text-muted small">كمية تقديرية</span>@endif
                                        </div>
                                        @if (data_get($priceDisplay, 'items.'.$index.'.unit_lyd') !== null)<p class="shein-secondary mb-0">سعر القطعة <bdi>{{ data_get($priceDisplay, 'items.'.$index.'.unit_lyd') }} LYD</bdi></p>@endif
                                    </div>
                                    <div class="shein-product-price"><span>إجمالي المنتج</span>
                                        <strong dir="ltr">@if (data_get($priceDisplay, 'items.'.$index.'.line_lyd') !== null){{ data_get($priceDisplay, 'items.'.$index.'.line_lyd') }} LYD
                                        @else
                                            {{ $line ? ($line['amount'] ?? '—').' '.($line['currency'] ?? '') : '—' }}@endif</strong>
                                        @if ($quotedItem)<small>شامل نسبة الشركة</small>@endif
                                    </div>
                                </article>
                            @empty
                                <div class="shein-empty"><i class="ti ti-shopping-bag shein-empty-icon" aria-hidden="true"></i><h5>لم تصل تفاصيل المنتجات بعد</h5><p class="text-muted mb-0">ستظهر الصور والأسعار هنا بعد تجهيز عرض السعر.</p></div>
                            @endforelse
                        </div>
                    </section>
                    @if ($pdfEditor)
                        @can('cart-edit')@include('admin.carts.partials.pdf-editor')@endcan
                    @endif

                    <div class="card">
                        <div class="card-header"><h5>{{ $cart->origin === 'manual' ? 'الرابط المدخل' : 'رسالة العميل' }}</h5></div>
                        <div class="card-body"><p class="mb-0" style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $cart->incomingMessage?->message }}</p></div>
                    </div>
                    @if ($cart->incomingMessage?->replyMessage)
                        <div class="card">
                            <div class="card-header"><h5>الرد على العميل</h5></div>
                            <div class="card-body"><p class="mb-0" style="white-space: pre-wrap; overflow-wrap: anywhere;">{{ $cart->incomingMessage->replyMessage->message }}</p></div>
                        </div>
                    @endif
                </div>
                <aside class="shein-detail-sidebar" id="cart-follow-up" aria-label="العميل والمتابعة">
                    <div class="card">
                        <div class="card-header"><h5>العميل</h5></div>
                        <div class="card-body">
                            <h5>{{ $cart->incomingMessage?->name ?? 'غير محدد' }}</h5>
                            <p class="mb-1">رقم واتساب العميل</p>
                            @if ($cart->contact_phone)
                                <p dir="ltr">{{ $cart->contact_phone }}</p>
                            @else
                                <p class="text-muted small">رقم الهاتف غير متاح من واتساب.</p>
                                <p class="text-muted small">معرّف العميل: <bdi>{{ $cart->incomingMessage?->from }}</bdi></p>
                            @endif
                            @if ($cart->whatsapp_url)
                                <a href="{{ $cart->whatsapp_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-success">فتح واتساب</a>
                            @endif
                            @include('admin.carts.partials.receiving-source', ['compact' => false])
                            <p class="text-muted mt-3 mb-0">{{ $cart->origin === 'manual' ? 'أضيفت السلة يدوياً في' : 'استلمنا السلة في' }} <span dir="ltr">{{ $cart->created_at->format('Y-m-d H:i') }}</span></p>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">متابعة العميل</h5>
                            @include('admin.carts.partials.operator-status')
                        </div>
                        <div class="card-body">
                            @if ($cart->whatsapp_queued_at)
                                <p class="mb-2">قرار العميل: <strong>{{ \App\Models\CartQuote::CUSTOMER_STATUSES[$cart->customer_status] ?? $cart->customer_status }}</strong></p>
                                @if ($cart->customer_status === 'confirmed')
                                    <p class="mb-2">طريقة الدفع: <strong>{{ \App\Models\CartQuote::PAYMENT_METHODS[$cart->customer_payment_method] ?? 'بانتظار اختيار العميل' }}</strong></p>
                                @endif
                                <p class="text-muted small" dir="ltr">{{ $cart->whatsapp_cart_reference }}</p>
                            @endif
                            <form method="post" action="{{ route('carts.update', $cart) }}">
                                @csrf
                                @method('PUT')
                                <div class="form-group">
                                    <label class="form-label" for="operator_status">حالة المتابعة</label>
                                    <select id="operator_status" name="operator_status" class="form-select @error('operator_status') is-invalid @enderror"
                                        @cannot('cart-edit') disabled @endcannot required>
                                        @foreach (\App\Models\CartQuote::OPERATOR_STATUSES as $status => $label)
                                            <option value="{{ $status }}" @selected(old('operator_status', $cart->operator_status) === $status)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('operator_status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="operator_notes">ملاحظات المتابعة</label>
                                    <textarea id="operator_notes" name="operator_notes" class="form-control @error('operator_notes') is-invalid @enderror"
                                        rows="3" maxlength="3000" placeholder="قرار العميل، تفاصيل التواصل أو سبب استبعاد السلة"
                                        @cannot('cart-edit') readonly @endcannot>{{ old('operator_notes', $cart->operator_notes) }}</textarea>
                                    @error('operator_notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                @can('cart-edit')<button type="submit" class="btn btn-primary">حفظ المتابعة</button>@endcan
                            </form>
                            @if ($cart->operator_updated_at)
                                <p class="text-muted small mt-3 mb-0">آخر متابعة بواسطة {{ $latestStatusAudit?->actor_name ?? $cart->handledBy?->name ?? 'مستخدم سابق' }}
                                    <span dir="ltr">{{ $cart->operator_updated_at->format('Y-m-d H:i') }}</span>
                                </p>
                            @endif
                        </div>
                    </div>
                    @include('admin.carts.partials.status-history')
                </aside>
            </div>
            <dialog id="product-image-viewer" class="shein-image-viewer" aria-label="صورة المنتج">
                <button type="button" data-image-close class="shein-viewer-close" aria-label="إغلاق الصورة"><i class="ti ti-x" aria-hidden="true"></i></button>
                <figure><img alt=""><figcaption></figcaption></figure>
            </dialog>
            @if ($cart->pdf_path)@include('admin.carts.partials.pdf-preview')@endif
        </div>
    </section>
</div>
@endsection

@push('script')
    @include('admin.carts.partials.image-script')
    @include('admin.carts.partials.pdf-preview-script')
@endpush
