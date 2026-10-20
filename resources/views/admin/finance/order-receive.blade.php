@extends('layout.master')

@section('title')
    <title>استلام بضاعة {{ $order->code }}</title>
@endsection

@section('style')
    @include('admin.finance._style')
@endsection

@php
    use App\Services\TreasuryService as TS;
    $f = fn ($n) => TS::fmt($n);
    $sign = $order->currency_sign ?: 'د.ل';
@endphp

@section('content')
    <section class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('order.index') }}">الطلبات</a></li>
                        <li class="breadcrumb-item">استلام بضاعة</li>
                    </ul>
                    <div class="page-header-title"><h2 class="mb-0">استلام بضاعة — {{ $order->code }}</h2></div>
                </div>
            </div>
            @include('layout.validation-messages')

            <form method="post" action="{{ route('order.receive.store', $order->id) }}">
                @csrf
                <div class="row g-3">
                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex flex-wrap gap-3 mb-3">
                                    <div class="fin-kpi flex-fill"><div class="lbl">الزبون</div><div class="fw-bold">{{ $order->customer->name }}</div><div class="sub">{{ $order->customer->phone_1 }} · كود {{ $order->customer->code }}</div></div>
                                    <div class="fin-kpi flex-fill"><div class="lbl">الرحلة</div><div class="fw-bold">{{ $order->trip_details ?: '—' }}</div><div class="sub">{{ $order->trip_status }}</div></div>
                                    <div class="fin-kpi flex-fill"><div class="lbl">القطع المطلوبة</div><div class="fw-bold">{{ $order->qty }}</div></div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-6 col-md-4">
                                        <label class="form-label">عدد القطع اللي وصلن</label>
                                        <input type="number" min="0" name="received_qty" class="form-control fin-big-input calc" value="{{ old('received_qty', $order->received_qty ?? $order->qty) }}" required>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <label class="form-label">عدد الطرود</label>
                                        <input type="number" min="1" name="bags_num" class="form-control fin-big-input calc" value="{{ old('bags_num', $order->bags_num ?: 1) }}" required>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <label class="form-label">الوزن (كيلو)</label>
                                        <input type="number" step="any" min="0" name="kilo" class="form-control fin-big-input calc" value="{{ old('kilo', $order->kilo) }}" required>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <label class="form-label">قيمة الشحن على الزبون ({{ $sign }})</label>
                                        <input type="number" step="any" min="0" name="ship_price" id="ship" class="form-control fin-big-input calc" value="{{ old('ship_price', $order->ship_price) }}" required>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <label class="form-label">مكان التخزين (الرف)</label>
                                        <input type="text" name="storage_num" class="form-control fin-big-input calc" value="{{ old('storage_num', $order->storage_num) }}" placeholder="مثلا A-3" required>
                                    </div>
                                    <div class="col-6 col-md-4">
                                        <label class="form-label">نوع الطرد</label>
                                        <select name="package_type" class="form-select calc" style="height:52px" required>
                                            @foreach ($packageTypes as $pt)
                                                <option value="{{ $pt }}" @selected(old('package_type', $order->package_type) == $pt)>{{ $pt }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>

                                <div class="fin-result mt-4">
                                    <div class="row-line"><span>قيمة الفاتورة</span><b>{{ $f($order->total_price - $order->discount) }} {{ $sign }}</b></div>
                                    <div class="row-line"><span>+ الشحن</span><b><span id="oShip">0</span> {{ $sign }}</b></div>
                                    <div class="row-line"><span>= الإجمالي مع الشحن</span><b><span id="oGrand">0</span> {{ $sign }}</b></div>
                                    <div class="row-line"><span>− المدفوع (العربون)</span><b class="text-success">{{ $f($order->paid_price) }} {{ $sign }}</b></div>
                                    <div class="row-line big"><span>المتبقي على الزبون</span><b class="text-danger"><span id="oRem">0</span> {{ $sign }}</b></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <div class="fin-card-title"><i class="ti ti-brand-whatsapp text-success me-2" style="font-size:22px"></i>الرسالة اللي بتوصل للزبون</div>
                                <div class="wa-preview" id="waPreview"></div>
                                <div class="form-check mt-3">
                                    <input class="form-check-input" type="checkbox" name="send_whatsapp" value="1" id="sendWa" checked>
                                    <label class="form-check-label" for="sendWa">ابعث الرسالة للزبون على واتساب</label>
                                </div>
                                <button class="btn btn-success btn-lg w-100 mt-3">تسجيل الاستلام</button>
                                <div class="fin-hint mt-2">تقدر تعدل نص الرسالة من الإعدادات ← رسائل الواتساب ← "رسالة وصول البضاعة".</div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </section>
@endsection

@section('script')
    <script>
        (function () {
            var tpl = @json($template ?? '');
            var base = {
                name: @json($order->customer->name), customer_code: @json($order->customer->code), order_code: @json($order->code),
                ordered_pieces: {{ (int) $order->qty }}, total: {{ (float) $order->total_price - (float) $order->discount }}, paid: {{ (float) $order->paid_price }},
                currency: @json($sign), app_url: @json(config('app.url'))
            };
            function fmt(n) { return (Math.round(n * 100) / 100).toString(); }
            function val(name) { var el = document.querySelector('[name="' + name + '"]'); return el ? el.value : ''; }
            function render() {
                var ship = parseFloat(val('ship_price')) || 0, grand = base.total + ship, rem = Math.max(0, grand - base.paid);
                document.getElementById('oShip').textContent = fmt(ship);
                document.getElementById('oGrand').textContent = fmt(grand);
                document.getElementById('oRem').textContent = fmt(rem);
                var v = Object.assign({}, base, {
                    pieces: val('received_qty'), parcels: val('bags_num'), weight: val('kilo'), shelf: val('storage_num'),
                    package_type: val('package_type'), ship_price: fmt(ship), grand_total: fmt(grand), remaining: fmt(rem), total: fmt(base.total), paid: fmt(base.paid)
                });
                document.getElementById('waPreview').textContent = (tpl || '').replace(/\{\{\$(\w+)\}\}/g, function (m, k) { return v[k] !== undefined ? v[k] : m; });
            }
            document.querySelectorAll('.calc').forEach(function (el) { el.addEventListener('input', render); el.addEventListener('change', render); });
            render();
        })();
    </script>
@endsection
