@extends('layout.master')

@section('title')
    <title> تعديل بيت الطلبات </title>
@endsection

@section('content')
    <section class="pc-container">

        <div class="pc-content">
            <!-- [ breadcrumb ] start -->
            <div class="page-header">
                <div class="page-block">
                    <div class="row align-items-center">
                        <div class="col-md-12">
                            <ul class="breadcrumb">
                                <li class="breadcrumb-item"><a href="{{ route('dashboard.index') }}">الرئيسية</a></li>
                                <li class="breadcrumb-item" aria-current="page">تعديل البيانات</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">تعديل بيانات الطلب <span class="text-primary">{{ $order->code }}</span>
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->

            @include('layout.validation-messages')

            <!-- [ Main Content ] start -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>تعديل البيانات</h5>
                        </div>
                        <div class="card-body">

                            <form action="{{ route('order.update', $order->id) }}" method="post"
                                enctype="multipart/form-data">
                                @method('PUT')

                                @csrf
                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">الزبون</label>
                                            <input type="text" disabled value="{{ $order->customer->name }}"
                                                class="form-control" id="exampleInputName343">
                                        </div>
                                    </div>

                                    {{-- <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="trip_select">الرحلة (إختياري)</label>
                                            <select name="trip_id" id="trip_select" class="form-control">
                                                <option  value="">-- اختر الرحلة --</option>
                                                @if ($order->trip)
                                                    <option value="{{ $order->trip->id }}" selected>{{ $order->trip->code }} - {{ $order->trip->country }}</option>
                                                @endif
                                                @foreach ($trips as $trip)
                                                @if ($order->trip_id != $trip->id)
                                                <option value="{{ $trip->id }}">{{ $trip->code }} - {{ $trip->country }}</option>
                                                @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div> --}}

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="trip_details">بيانات الرحلة</label>
                                            <input type="text" name="trip_details" value="{{ $order->trip_details }}"
                                                class="form-control" id="trip_details" placeholder="بيانات الرحلة">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="platform_select">المنصة </label>
                                            <select name="platform_id" required id="platform_select" class="form-control">
                                                <option value="">-- اختر المنصة --</option>
                                                @foreach ($platforms as $platform)
                                                    @if ($order->platform_id && $order->platform_id == $platform->id)
                                                        <option value="{{ $platform->id }}" selected>{{ $platform->name }}
                                                        </option>
                                                    @else
                                                        <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">الكمية</label>
                                            <input type="number" value="{{ $order->qty }}" name="qty"
                                                class="form-control" id="exampleInputName343" required placeholder="الكمية"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال الكمية')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="order_type">نوع الطرد</label>
                                            <input type="text" name="order_type" value="{{ $order->order_type }}"
                                                class="form-control" id="order_type" placeholder="نوع الطرد">
                                        </div>
                                    </div>



                                    {{-- <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">اجمالي سعر
                                                التكلفة</label>
                                            <input type="number" step="any" name="cost_price"
                                                class="form-control" value="{{ $order->cost_price }}" id="exampleInputName343" required
                                                placeholder="اجمالي سعر التكلفة"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال اجمالي سعر التكلفة')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div> --}}

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">اجمالي سعر
                                                البضاعة</label>
                                            <input type="number" value="{{ $order->total_price }}" step="any"
                                                name="total_price" class="form-control" id="exampleInputName343" required
                                                placeholder="اجمالي سعر البضاعة"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال اجمالي سعر البضاعة')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">عدد الطرود
                                                (إختياري)</label>
                                            <input type="number" value="{{ $order->bags_num }}" name="bags_num"
                                                class="form-control" id="exampleInputName343" placeholder="عدد الطرود">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">الكمية في الطرود
                                                (إختياري)</label>
                                            <input type="number" value="{{ $order->bags_qty }}" name="bags_qty"
                                                class="form-control" id="exampleInputName343"
                                                placeholder="الكمية في الطرود">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">اجمالي سعر الشحن
                                                (إختياري)</label>
                                            <input type="number" value="{{ $order->ship_price }}" step="any"
                                                value="0" name="ship_price" class="form-control"
                                                id="exampleInputName343" placeholder="سعر الشحن للكيلو">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">الوزن بالكيلو
                                                (إختياري)</label>
                                            <input type="number" value="{{ $order->kilo }}" step="any"
                                                name="kilo" class="form-control" id="exampleInputName343"
                                                placeholder="الوزن بالكيلو">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="storage_num">رقم التخزين
                                                (إختياري)</label>
                                            <input type="number" value="{{ $order->storage_num }}" name="storage_num"
                                                class="form-control" id="storage_num" placeholder="رقم التخزين">
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="currency">العملة </label>
                                            <select name="currency" required id="currency" class="form-control">
                                                <option>-- اختر العملة --</option>
                                                @if ($order->currency == 'dinar')
                                                    <option selected value="dinar">دينار ليبي - د.ل</option>
                                                    <option value="dollar">دولار - $</option>
                                                    <option value="euro">يورو - €</option>
                                                @elseif($order->currency == 'dollar')
                                                    <option value="dinar">دينار ليبي - د.ل</option>
                                                    <option selected value="dollar">دولار - $</option>
                                                    <option value="euro">يورو - €</option>
                                                @elseif($order->currency == 'euro')
                                                    <option value="dinar">دينار ليبي - د.ل</option>
                                                    <option value="dollar">دولار - $</option>
                                                    <option selected value="euro">يورو - €</option>
                                                @else
                                                    <option selected value="dinar">دينار ليبي - د.ل</option>
                                                    <option value="dollar">دولار - $</option>
                                                    <option value="euro">يورو - €</option>
                                                @endif
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">ملاحطات (إختياري)</label>
                                            <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                placeholder="ملاحظات (إختياري)">{{ $order->note }}</textarea>
                                        </div>
                                    </div>


                                    <div id="addedItems">
                                        @foreach ($order->items as $item)
                                            <div class="row itemRow">
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label class="form-label" for="item_name_{{ $loop->index }}">اسم
                                                            المنتج {{ $loop->iteration }}</label>
                                                        <input type="text" name="items[name][]" required
                                                            value="{{ $item->name }}" class="form-control"
                                                            id="item_name_{{ $loop->index }}" placeholder="اسم المنتج">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label class="form-label"
                                                            for="item_quantity_{{ $loop->index }}">الكمية</label>
                                                        <input type="number" name="items[quantity][]" required
                                                            value="{{ $item->quantity }}" class="form-control" oninput="calculateTotals()"
                                                            id="item_quantity_{{ $loop->index }}" placeholder="الكمية">
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-group">
                                                        <label class="form-label"
                                                            for="item_price_{{ $loop->index }}">إجمالي السعر</label>
                                                        <input type="number" step="any" name="items[price][]"
                                                            required value="{{ $item->price }}" class="form-control"
                                                            id="item_price_{{ $loop->index }}" oninput="calculateTotals()"
                                                            placeholder="إجمالي السعر">
                                                    </div>
                                                </div>
                                                <div class="col-md-2 d-flex justify-content-end m-auto">
                                                                <button type="button" onclick="removeItem(this)" class="btn btn-danger">حذف المنتج</button>

                                                </div>
                                            </div>
                                        @endforeach

                                    </div>
                                    <div class="col-md-12 d-flex justify-content-center">
                                        <div class="form-group">
                                            <button type="button" onclick="addItem()"
                                                class="btn btn-light-info mb-4">اضافة المنتج</button>
                                        </div>
                                    </div>


                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary mb-4">حفظ التعديلات</button>
                                    </div>
                                </div>

                            </form>
                        </div>
                    </div>
                </div>

            </div>
            <!-- [ form-element ] end -->
        </div>
        </div>
    </section>
@endsection


@section('script')
    <script>

                function calculateTotals() {
    let totalQty = 0;
    let totalPrice = 0;

    // جمع الكميات
    $('input[name="items[quantity][]"]').each(function () {
        let value = parseFloat($(this).val());
        if (!isNaN(value)) {
            totalQty += value;
        }
    });

    // جمع الأسعار
    $('input[name="items[price][]"]').each(function () {
        let value = parseFloat($(this).val());
        if (!isNaN(value)) {
            totalPrice += value;
        }
    });

    // وضع القيم في الحقول الرئيسية
    $('input[name="qty"]').val(totalQty);
    $('input[name="total_price"]').val(totalPrice.toFixed(2));
}

        // 1. Define the variable globally
        let addItemCount = {{ $order->items->count() }};

        function addItem() {
    addItemCount++;
    var itemHtml = `<div class="row itemRow">
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">اسم المنتج ${addItemCount}</label>
                <input type="text" name="items[name][]" required class="form-control" placeholder="اسم المنتج">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">الكمية</label>
                <input type="number" name="items[quantity][]" required
                    class="form-control item-qty"
                    placeholder="الكمية" oninput="calculateTotals()">
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label class="form-label">إجمالي السعر</label>
                <input type="number" step="any"
                    name="items[price][]" required
                    class="form-control item-price"
                    placeholder="إجمالي السعر"
                    oninput="calculateTotals()">
            </div>
        </div>
        <div class="col-md-2 d-flex justify-content-end m-auto">
            <button type="button" onclick="removeItem(this)" class="btn btn-danger">حذف المنتج</button>
        </div>
    </div>`;

    $('#addedItems').append(itemHtml);
}

function removeItem(button) {
    $(button).closest('.itemRow').remove();
    calculateTotals(); // إعادة الحساب بعد الحذف
}



        function toggleMoreFields() {
    let more = document.getElementById("moreFields");

    if (more.style.display === "none") {
        more.style.display = "flex";   // 👈 هذا الحل
    } else {
        more.style.display = "none";
    }
}

    </script>
@endsection
