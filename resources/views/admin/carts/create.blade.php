@extends('sg.layout')
@php $nav = $nav ?? 'cart'; @endphp
@push('style')
<link rel="stylesheet" href="{{ asset('assets/sg/compat.css') }}?v=1">
@endpush

@php
    $isAmazon = $retailer === 'amazon';
    $isIherb = $retailer === 'iherb';
    $formTitle = $isAmazon ? 'فحص منتج أمازون' : ($isIherb ? 'فحص آي هيرب' : 'فحص سلة شي إن');
@endphp

@section('title'){{ $formTitle }}@endsection

@section('content')
<div class="legacy">
<div class="top" style="justify-content:flex-end;margin-bottom:0"><div class="acts"><button class="sg-burger">☰</button></div></div>
    <section class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('carts.index') }}">السلات</a></li>
                        <li class="breadcrumb-item" aria-current="page">فحص سلة</li>
                    </ul>
                    <div class="page-header-title"><h2 class="mb-0">{{ $formTitle }}</h2></div>
                </div>
            </div>

            @include('layout.validation-messages')

            @unless ($storeEnabled)
                <div class="alert alert-warning">طلبات {{ \App\Models\CartQuote::RETAILERS[$retailer] }} متوقفة حالياً.
                    @canany(['system-list', 'system-edit'])<a href="{{ route('settings.edit') }}">تفعيل المتجر من إعدادات النظام</a>@endcanany
                </div>
            @endunless

            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header"><h5>{{ $isAmazon ? 'إضافة منتج أمازون للفحص والمتابعة' : 'إضافة سلة للفحص والمتابعة' }}</h5></div>
                        <div class="card-body">
                            <p class="text-muted">{{ $isAmazon ? 'أدخل رابط منتج أمازون ورقم واتساب العميل والكمية المطلوبة. سيظهر عرض السعر هنا لمراجعته ومتابعة العميل.' : 'أدخل رابط السلة ورقم واتساب العميل. سنطلب عرض السعر ونضيف السلة إلى قائمة المتابعة.' }}</p>
                            <form method="post" action="{{ route('carts.store') }}" id="check-cart-form">
                                @csrf
                                <input type="hidden" name="retailer" value="{{ $retailer }}">
                                <input type="hidden" name="submission_key" value="{{ $submissionKey }}">
                                <div class="form-group">
                                    <label class="form-label" for="source_url">{{ $isAmazon ? 'رابط منتج أمازون' : ($isIherb ? 'رابط منتج أو سلة آي هيرب' : 'رابط سلة شي إن') }}</label>
                                    <input type="url" id="source_url" name="source_url" dir="ltr" required maxlength="8192"
                                        class="form-control @error('source_url') is-invalid @enderror" value="{{ is_string(old('source_url')) ? old('source_url') : '' }}"
                                        placeholder="{{ $isAmazon ? 'https://www.amazon.ae/dp/...' : ($isIherb ? 'https://iherb.co/XXXXXXXX' : 'https://api-shein.shein.com/h5/sharejump/...') }}" aria-describedby="source-url-help">
                                    <small id="source-url-help" class="form-text text-muted">{{ $isAmazon ? 'افتح المنتج في أمازون واضغط مشاركة. يتم التسعير على '.\App\Models\CartQuote::amazonSite().'، برابط واحد لكل منتج.' : ($isIherb ? 'انسخ رابط المنتج أو استخدم السلة ← مشاركة في آي هيرب. رابط سلتك الشخصية وصفحات البحث والتصنيفات غير مقبولة. استخدم https://.' : 'انسخ رابط مشاركة السلة من شي إن.') }}</small>
                                    @error('source_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                @if ($isAmazon)
                                    <div class="form-group">
                                        <label class="form-label" for="requested_quantity">الكمية المطلوبة</label>
                                        <input type="number" id="requested_quantity" name="requested_quantity" min="1" max="999999" step="1" required
                                            value="{{ old('requested_quantity', 1) }}" class="form-control @error('requested_quantity') is-invalid @enderror">
                                        <small class="text-muted">يسعر المزود قطعة واحدة؛ نضرب سعر العميل في الكمية المطلوبة.</small>
                                        @error('requested_quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    @include('admin.carts.partials.amazon-pricing')
                                @endif
                                <div class="form-group">
                                    <label class="form-label" for="phone">رقم واتساب العميل</label>
                                    <input type="tel" id="phone" name="phone" dir="ltr" inputmode="tel" required maxlength="40"
                                        class="form-control @error('phone') is-invalid @enderror" value="{{ is_string(old('phone')) ? old('phone') : '' }}"
                                        placeholder="218911234567" aria-describedby="phone-help">
                                    <small id="phone-help" class="form-text text-muted">اكتب الرقم مع رمز الدولة. مثال: 218911234567 أو +218 91 1234567.</small>
                                    @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary" @disabled(! $storeEnabled)>{{ $isAmazon ? 'فحص وإضافة المنتج' : 'فحص وإضافة السلة' }}</button>
                                    <a href="{{ route('carts.index') }}" class="btn btn-outline-secondary">رجوع للقائمة</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection

@push('script')
    <script>
        const checkCartForm = document.getElementById('check-cart-form');
        const checkCartButton = checkCartForm.querySelector('button[type="submit"]');
        checkCartForm.addEventListener('submit', () => {
            checkCartButton.disabled = true;
            checkCartButton.textContent = 'جاري طلب الفحص...';
        });
        window.addEventListener('pageshow', () => {
            checkCartButton.disabled = @json(! $storeEnabled);
            checkCartButton.textContent = @json($isAmazon ? 'فحص وإضافة المنتج' : 'فحص وإضافة السلة');
        });
    </script>
@endpush
