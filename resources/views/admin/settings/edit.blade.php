@extends('sg.layout')
@php $nav = $nav ?? 'stores'; @endphp

@section('title')إعدادات المتاجر وعروض الأسعار@endsection

@push('style')
<link rel="stylesheet" href="{{ asset('assets/sg/compat.css') }}?v=1">
    @include('admin.settings.partials.page-styles')
@endpush

@section('content')
<div class="legacy">
<div class="top" style="justify-content:flex-end;margin-bottom:0"><div class="acts"><button class="sg-burger">☰</button></div></div>
    <section class="pc-container retailer-settings">
        <div class="pc-content">
            <div class="settings-container">
                <div class="page-header">
                    <div class="page-block">
                        <ul class="breadcrumb">
                            <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">الرئيسية</a></li>
                            <li class="breadcrumb-item" aria-current="page">إعدادات النظام</li>
                        </ul>
                        <div class="settings-heading">
                            <div>
                                <h2>إعدادات المتاجر</h2>
                                <p>إدارة التسعير ورسائل العملاء من مكان واحد.</p>
                            </div>
                            @cannot('system-edit')
                                <span class="settings-readonly"><i class="ti ti-lock" aria-hidden="true"></i> عرض فقط</span>
                            @endcannot
                        </div>
                    </div>
                </div>

                @include('layout.validation-messages')

                <form action="{{ route('settings.update') }}" method="post" id="retailer-settings-form" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <section class="settings-surface settings-panel mb-4" aria-labelledby="store-enabled-title">
                        <div class="settings-panel-heading">
                            <div><h3 id="store-enabled-title">تفعيل المتاجر</h3><p id="store-enabled-help">فعّل المتاجر التي تستقبل طلبات تسعيرها من واتساب أو تضيفها يدوياً. عند إيقاف متجر، تتوقف الطلبات الجديدة وتنتظر الطلبات المحفوظة التي لم ترسل للفحص حتى إعادة تفعيله. العروض التي بدأ المزود تجهيزها تستكمل تلقائياً.</p></div>
                        </div>
                        <div class="settings-field-grid">
                            @foreach (\App\Models\CartQuote::RETAILERS as $store => $storeLabel)
                                @php($storeField = $store.'_enabled')
                                <div>
                                    <input type="hidden" name="{{ $storeField }}" value="0">
                                    <div class="form-check form-switch">
                                        <input type="checkbox" class="form-check-input" role="switch" name="{{ $storeField }}" id="{{ $storeField }}" value="1"
                                            @checked(old($storeField, $settings->isCartStoreEnabled($store)))
                                            @cannot('system-edit') disabled @endcannot
                                            aria-describedby="store-enabled-help" @error($storeField) aria-invalid="true" @enderror>
                                        <label class="form-check-label" for="{{ $storeField }}">تفعيل {{ $storeLabel }}</label>
                                    </div>
                                    @error($storeField)<div class="text-danger">{{ $message }}</div>@enderror
                                </div>
                            @endforeach
                        </div>
                    </section>

                    <section class="settings-surface settings-exchange" aria-labelledby="exchange-title">
                        <div>
                            <span class="settings-kicker">مشترك بين المتاجر</span>
                            <h3 id="exchange-title">سعر صرف الدولار</h3>
                            <p>يستخدم لتحويل عروض شي إن وأمازون وآي هيرب إلى الدينار الليبي.</p>
                            @if ($settings->usd_to_lyd_rate !== null)
                                <span class="settings-saved-rate" dir="ltr">1 USD = {{ rtrim(rtrim($settings->usd_to_lyd_rate, '0'), '.') }} LYD</span>
                            @else
                                <span class="settings-muted">لم يتم تحديد سعر الصرف بعد.</span>
                            @endif
                        </div>
                        @include('admin.settings.partials.number-field', [
                            'field' => 'usd_to_lyd_rate', 'label' => 'سعر الصرف (دينار لكل دولار)',
                            'unit' => 'LYD', 'min' => '0.000001', 'max' => '999999.999999', 'step' => '0.000001',
                            'help' => 'يمكن إدخال السعر بدقة تصل إلى 6 منازل عشرية.',
                        ])
                    </section>

                    <section class="settings-surface settings-wallet" aria-labelledby="wallet-title">
                        <span class="settings-kicker">مشترك بين جميع السلات</span>
                        <h3 id="wallet-title">رصيد المحفظة وردود السلات</h3>
                        <input type="hidden" name="shein_pause_on_empty_wallet" value="0">
                        <div class="form-check form-switch">
                            <input type="checkbox" name="shein_pause_on_empty_wallet" id="shein_pause_on_empty_wallet"
                                value="1" role="switch" class="form-check-input"
                                @checked(old('shein_pause_on_empty_wallet', $settings->shein_pause_on_empty_wallet))
                                aria-describedby="wallet-pause-help" @error('shein_pause_on_empty_wallet') aria-invalid="true" @enderror
                                @cannot('system-edit') disabled @endcannot>
                            <label class="form-check-label" for="shein_pause_on_empty_wallet">إيقاف ردود جميع السلات عند نفاد رصيد المحفظة</label>
                        </div>
                        <p id="wallet-pause-help">يشمل جميع السلات، بما فيها شي إن وأمازون. تحفظ السلات وتستأنف تلقائياً بعد شحن المحفظة.</p>
                        <details class="settings-details">
                            <summary>تفاصيل الإيقاف والاستئناف</summary>
                            <div class="settings-details-body"><p>عندما يصبح الرصيد المتاح صفراً، تحفظ السلات دون إرسالها للفحص أو إرسال رسالة الاستلام أو ملف العرض. يعاد التحقق كل دقيقة وتستأنف تلقائياً بعد شحن المحفظة. يتوقف إرسال ردود السلات أيضاً عند تعذر التحقق من الرصيد. الردود الأخرى لا تتأثر.</p></div>
                        </details>
                        @error('shein_pause_on_empty_wallet')<div class="text-danger">{{ $message }}</div>@enderror
                    </section>

                    <div class="settings-surface settings-workspace">
                        <div class="settings-tabs" role="tablist" aria-label="أقسام إعدادات المتاجر" hidden>
                            @foreach ([
                                'shein' => ['شي إن', 'ti-basket'],
                                'amazon' => ['أمازون', 'ti-shopping-cart'],
                                'iherb' => ['آي هيرب', 'ti-leaf'],
                                'messages' => ['رسائل واتساب', 'ti-message'],
                                'replies' => ['ردود الأزرار', 'ti-click'],
                            ] as $section => [$label, $icon])
                                <button type="button" id="settings-tab-{{ $section }}" class="settings-tab" role="tab"
                                    aria-controls="settings-panel-{{ $section }}" aria-selected="false" tabindex="-1">
                                    <i class="ti {{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>

                        <section id="settings-panel-shein" class="settings-panel" aria-labelledby="settings-shein-title" data-settings-panel>
                            <div class="settings-panel-heading">
                                <div><span class="settings-kicker" dir="ltr">SHEIN</span><h3 id="settings-shein-title">إعدادات عروض شي إن</h3><p>نسبة الشركة وملاحظات عرض السعر.</p></div>
                                @can('cart-list')<a href="{{ route('carts.index', ['retailer' => 'shein']) }}" class="settings-link">عرض سلات شي إن <i class="ti ti-arrow-up-left" aria-hidden="true"></i></a>@endcan
                            </div>

                            <div class="settings-row">
                                <div class="settings-row-description"><h4>نسبة الشركة</h4><p>تضاف إلى سعر كل قطعة قبل التحويل إلى الدينار.</p></div>
                                @include('admin.settings.partials.number-field', [
                                    'field' => 'shein_company_percentage', 'label' => 'نسبة الشركة على كل قطعة من شي إن (%)',
                                    'unit' => '%', 'min' => '0', 'max' => '1000', 'step' => '0.0001',
                                    'help' => 'أدخل 0 لاحتساب السعر دون إضافة نسبة.',
                                ])
                            </div>
                            <details class="settings-details">
                                <summary>طريقة حساب كل قطعة</summary>
                                <div class="settings-details-body"><p>سعر القطعة بالدولار × (1 + نسبة الشركة ÷ 100) × سعر الصرف.</p><p>تعرض الأسعار بمنزلتين عشريتين مع الاحتفاظ بدقة الحساب. يقرب الإجمالي النهائي بالدينار فقط إلى أقرب دينار صحيح. تحفظ الإعدادات المستخدمة مع كل عرض.</p></div>
                            </details>

                            <details class="settings-details settings-pdf" @if($errors->has('shein_calculation_note')) open @endif>
                                <summary><span>ملاحظات العميل داخل ملف PDF<small>شرح حساب السلة الذي يظهر في عرض السعر</small></span></summary>
                                <div class="settings-details-body">
                                    @include('admin.settings.partials.message-field', [
                                        'field' => 'shein_calculation_note', 'label' => 'شرح حساب السلة وملاحظات العميل داخل PDF',
                                        'value' => $settings->calculationNote(), 'max' => 4000, 'rows' => 5,
                                        'help' => 'يحفظ هذا النص مع كل عرض جديد من شي إن.',
                                    ])
                                </div>
                            </details>
                        </section>

                        <section id="settings-panel-amazon" class="settings-panel" aria-labelledby="settings-amazon-title" data-settings-panel>
                            <div class="settings-panel-heading">
                                <div><span class="settings-kicker" dir="ltr">AMAZON · {{ \App\Models\CartQuote::amazonSite() }}</span><h3 id="settings-amazon-title">إعدادات تسعير أمازون</h3><p>إضافة ثابتة لكل قطعة، وهامش يعتمد على سعر المنتج الأساسي.</p></div>
                                @can('cart-list')<a href="{{ route('carts.index', ['retailer' => 'amazon']) }}" class="settings-link">عرض سلات أمازون <i class="ti ti-arrow-up-left" aria-hidden="true"></i></a>@endcan
                            </div>

                            <div class="settings-field-grid">
                                @foreach ([
                                    'amazon_fixed_fee_usd' => ['الإضافة الثابتة لكل قطعة', 'USD', '0', 'تضاف إلى كل قطعة مهما كان سعرها.'],
                                    'amazon_threshold_usd' => ['حد سعر المنتج الأساسي', 'USD', '0.0001', 'يحدد متى ينتقل الهامش من مبلغ ثابت إلى نسبة.'],
                                    'amazon_flat_surcharge_usd' => ['هامش المنتجات الأقل من الحد', 'USD', '0', 'مبلغ ثابت عندما يكون سعر المنتج أقل من الحد.'],
                                    'amazon_percentage' => ['هامش المنتجات عند الحد أو أكثر', '%', '0', 'نسبة من سعر المنتج الأساسي عندما يبلغ الحد أو يتجاوزه.'],
                                ] as $field => [$label, $unit, $min, $help])
                                    @include('admin.settings.partials.number-field', [
                                        'field' => $field, 'label' => $label, 'unit' => $unit, 'min' => $min,
                                        'max' => $unit === '%' ? '1000' : '999999.9999', 'step' => '0.0001', 'help' => $help,
                                    ])
                                @endforeach
                            </div>
                            <p class="settings-note"><i class="ti ti-info-circle" aria-hidden="true"></i> تطبق القواعد الجديدة على عروض أمازون الجديدة فقط. يحتفظ كل عرض بسعر الصرف والقواعد التي حسب بها.</p>
                            <details class="settings-details">
                                <summary>تفاصيل الحساب بالإعدادات المحفوظة</summary>
                                <div class="settings-details-body">@include('admin.carts.partials.amazon-pricing')</div>
                            </details>

                            <div class="settings-panel-heading mt-4">
                                <div><h3>رسائل حالات أمازون في واتساب</h3><p>رسالة واحدة لكل حالة، مثل شي إن. يرسل النص المحفوظ لجميع العملاء، وتطبق التغييرات بعد الحفظ على الردود الجديدة. يمكن إرسال عدة روابط في رسالة واحدة؛ يجهز عرض مستقل لكل رابط.</p></div>
                            </div>
                            @foreach (\App\Support\AmazonMessageTemplates::DEFINITIONS as $status => $definition)
                                <div class="settings-message-row">
                                    @include('admin.settings.partials.message-field', [
                                        'field' => 'amazon-message-'.$status,
                                        'name' => 'amazon_messages['.$status.']',
                                        'errorKey' => 'amazon_messages.'.$status,
                                        'direction' => 'auto', 'label' => $definition['label'],
                                        'value' => $settings->amazonMessageTemplate($status),
                                        'max' => 2000, 'rows' => $status === 'succeeded' ? 4 : 3,
                                        'help' => $definition['help'],
                                    ])
                                </div>
                            @endforeach
                        </section>

                        <section id="settings-panel-iherb" class="settings-panel" aria-labelledby="settings-iherb-title" data-settings-panel>
                            <div class="settings-panel-heading">
                                <div><span class="settings-kicker" dir="ltr">iHerb</span><h3 id="settings-iherb-title">رسائل آي هيرب في واتساب</h3><p>رسالة مستقلة لكل حالة للمنتجات والسلات المشتركة. يستخدم النص المحفوظ في الردود الجديدة.</p></div>
                                @can('cart-list')<a href="{{ route('carts.index', ['retailer' => 'iherb']) }}" class="settings-link">عرض سلات آي هيرب <i class="ti ti-arrow-up-left" aria-hidden="true"></i></a>@endcan
                            </div>
                            @foreach (\App\Support\IherbMessageTemplates::DEFINITIONS as $status => $definition)
                                @if ($status === 'confirm')
                                    <div class="settings-panel-heading mt-4"><div><h3>ردود أزرار آي هيرب</h3><p>ترفق أزرار تأكيد الطلب وتعديل الكميات وإلغاء الطلب مع ملف PDF. تحدث حالة الطلب تلقائياً ويصل إلى العميل الرد المحفوظ لكل زر.</p></div></div>
                                @endif
                                <div class="settings-message-row">
                                    @include('admin.settings.partials.message-field', [
                                        'field' => 'iherb-message-'.$status,
                                        'name' => 'iherb_messages['.$status.']',
                                        'errorKey' => 'iherb_messages.'.$status,
                                        'direction' => 'auto', 'label' => $definition['label'],
                                        'value' => $settings->iherbMessageTemplate($status),
                                        'max' => 2000, 'rows' => $status === 'succeeded' ? 4 : 3,
                                        'help' => $definition['help'],
                                    ])
                                </div>
                            @endforeach
                        </section>

                        <section id="settings-panel-messages" class="settings-panel" aria-labelledby="settings-messages-title" data-settings-panel>
                            <div class="settings-panel-heading"><div><span class="settings-kicker">رسائل شي إن</span><h3 id="settings-messages-title">رسائل واتساب</h3><p>النصوص التي تصل إلى العميل أثناء تجهيز عرض شي إن.</p></div></div>
                            @foreach ([
                                'shein_received_message' => ['رسالة استلام رابط السلة', $settings->receivedMessage(), 'ترسل بعد استلام رابط سلة شي إن للفحص.'],
                                'shein_product_message' => ['الرد على روابط المنتجات بدلاً من السلة', $settings->productMessage(), 'توضح للعميل ضرورة إرسال رابط السلة بدلاً من رابط منتج.'],
                                'shein_completed_message' => ['رسالة إرسال ملف عرض السعر', $settings->completedMessage(), 'ترفق مع ملف PDF بعد اكتمال عرض السعر.'],
                            ] as $field => [$label, $value, $help])
                                <div class="settings-message-row">
                                    @include('admin.settings.partials.message-field', ['field' => $field, 'label' => $label, 'value' => $value, 'max' => 2000, 'rows' => 3, 'help' => $help])
                                </div>
                            @endforeach
                        </section>

                        <section id="settings-panel-replies" class="settings-panel" aria-labelledby="settings-replies-title" data-settings-panel>
                            <div class="settings-panel-heading"><div><span class="settings-kicker">شي إن وأمازون</span><h3 id="settings-replies-title">ردود أزرار السلة في واتساب</h3><p>الرسالة التي تصل إلى العميل بعد الضغط على كل زر في عروض شي إن وأمازون. ردود آي هيرب مستقلة في قسم آي هيرب.</p></div></div>
                            <p id="cart-reply-help" class="settings-note">استخدم <code dir="ltr">{cart_ref}</code> لإضافة رقم السلة تلقائياً، مثلاً <bdi>CART-1042</bdi>.</p>
                            @foreach (['confirm' => 'الرد بعد تأكيد الطلب', 'edit' => 'الرد بعد طلب تعديل الكميات', 'cancel' => 'الرد بعد إلغاء الطلب'] as $action => $label)
                                <div class="settings-message-row">
                                    @include('admin.settings.partials.message-field', [
                                        'field' => 'shein_'.$action.'_reply', 'label' => $label, 'value' => $settings->cartActionReplyTemplate($action),
                                        'max' => 2000, 'rows' => 3, 'help' => 'يمكن تضمين {cart_ref} في هذا الرد.',
                                    ])
                                </div>
                            @endforeach
                            <h4 class="mt-4">رسائل اختيار طريقة الدفع لجميع المتاجر</h4>
                            <p class="settings-note">بعد تأكيد الطلب يظهر زرا الدفع نقدًا والتحويل البنكي. خصص الرد لكل خيار، وأضف بيانات الحساب البنكي في رسالة التحويل إذا رغبت.</p>
                            @foreach (\App\Models\CartQuote::PAYMENT_METHODS as $method => $label)
                                <div class="settings-message-row">
                                    @include('admin.settings.partials.message-field', [
                                        'field' => 'cart_'.$method.'_reply', 'label' => 'الرد بعد اختيار '.$label,
                                        'value' => $settings->cartPaymentReplyTemplate($method), 'direction' => 'rtl',
                                        'max' => 2000, 'rows' => 4, 'help' => 'يمكن تضمين {cart_ref} لإضافة رقم السلة تلقائياً.',
                                    ])
                                </div>
                            @endforeach
                            <p class="settings-muted mb-0">تطبق الرسائل المحفوظة على ردود الأزرار الجديدة. يبقى تحديث حالة السلة وسجل التغييرات تلقائياً.</p>
                        </section>
                    </div>

                    @can('system-edit')
                        <div class="settings-savebar">
                            <p id="settings-save-status" role="status" aria-live="polite">تطبق التغييرات في جميع الأقسام بعد الحفظ.</p>
                            <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy" aria-hidden="true"></i> حفظ التغييرات</button>
                        </div>
                    @endcan
                </form>
            </div>
        </div>
    </section>
</div>
@endsection

@push('script')
    @include('admin.settings.partials.page-script')
@endpush
