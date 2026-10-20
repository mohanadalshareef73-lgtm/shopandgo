<div class="card-body">
    <div class="dt-responsive table-responsive">
        <div id="autofill_wrapper" class="dataTables_wrapper dt-bootstrap5">
            <div class="row dt-row">
                <div class="col-sm-12">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1" colspan="1"
                                    style="width: 175.717px;" aria-label="Name: activate to sort column ascending">
                                    #
                                </th>
                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Name: activate to sort column ascending">
                                    كود الطلب
                                </th>
                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Product: activate to sort column ascending">
                                    الزبون
                                </th>
                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Product: activate to sort column ascending">
                                    نوع الطرد
                                </th>

                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Product: activate to sort column ascending">
                                    حالة الطلب
                                </th>


                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Product: activate to sort column ascending">
                                    المنتجات
                                </th>

                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Product: activate to sort column ascending">
                                    كمية القطع
                                </th>

                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Product: activate to sort column ascending">
                                    اجمالي سعر البضاعة
                                </th>

                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Product: activate to sort column ascending">
                                    القيمة المتبقية
                                </th>

                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Product: activate to sort column ascending">
                                    وزن البضاعة وسعر الشحن
                                </th>

                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Product: activate to sort column ascending">
                                    الرحلة
                                </th>

                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 175.717px;"
                                    aria-label="Product: activate to sort column ascending">
                                    ملاحظات
                                </th>

                                <th class="sorting" tabindex="0" aria-controls="autofill" rowspan="1"
                                    colspan="1" style="width: 285.883px;"
                                    aria-label="Position: activate to sort column ascending">
                                    الاجراءات
                                </th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($orders as $order)
                                <tr class="odd">

                                    <td>
                                        <a href="{{ route('order.print', $order->id) }}" class="btn btn-light-primary"
                                            target="_blank"><i class="fas fa-print"></i>
                                        </a>
                                    </td>

                                    <td class="">{{ $order->code }}
                                        <br> {{ $order->platform->name }}
                                    </td>

                                    <td class="">{{ $order->customer->name }}
                                        <br>
                                        <a style="color: green" href="https://wa.me/{{ $order->customer->phone_1 }}"
                                            target="_blank">{{ $order->customer->code }}</a>
                                    </td>
                                    <td class="">
                                        {{ $order->order_type ? $order->order_type : '-' }}
                                        @if ($order->storage_num)
                                            <br><span class="text-primary">{{ $order->storage_num }}
                                            </span>رقم التخزين
                                        @endif
                                    </td>
                                    @if ($order->status == 'paid_full')
                                        <td class="text-info"> مدفوعة
                                            @if ($order->delivered)
                                                <br><span class="text-success">تم التسليم
                                                    {{ $order->delivered_at }}</span>
                                                <br> <span
                                                    class="text-primary">{{ $order->delivery_type ?? $order->delivery_type }}
                                                    - {{ $order->delivery_name }}</span>
                                            @elseif($order->arrived)
                                                <br><span class="text-primary">وصلت</span> - <span
                                                    class="text-primary">{{ $order->arrived_at }}</span>
                                            @endif
                                        </td>
                                    @elseif ($order->status == 'unpaid')
                                        <td class="text-warning"> غير مدفوعة
                                            @if ($order->delivered)
                                                <br><span class="text-success">تم التسليم
                                                    {{ $order->delivered_at }}</span>
                                                <br> <span
                                                    class="text-primary">{{ $order->delivery_type ?? $order->delivery_type }}
                                                    - {{ $order->delivery_name }}</span>
                                            @elseif($order->arrived)
                                                <br><span class="text-primary">وصلت</span> - <span
                                                    class="text-primary">{{ $order->arrived_at }}</span>
                                            @endif
                                        </td>
                                    @elseif ($order->status == 'paid')
                                        <td class="text-info"> مدفوعة من غير الشحن
                                            @if ($order->delivered)
                                                <br><span class="text-success">تم التسليم
                                                    {{ $order->delivered_at }}</span>
                                                <br> <span
                                                    class="text-primary">{{ $order->delivery_type ?? $order->delivery_type }}
                                                    - {{ $order->delivery_name }}</span>
                                            @elseif($order->arrived)
                                                <br><span class="text-primary">وصلت</span> - <span
                                                    class="text-primary">{{ $order->arrived_at }}</span>
                                            @endif
                                        </td>
                                    @elseif ($order->status == 'canceld')
                                        <td class="text-danger"> ملغية
                                            @if ($order->delivered)
                                                <br><span class="text-success">تم التسليم
                                                    {{ $order->delivered_at }}</span>
                                                <br> <span
                                                    class="text-primary">{{ $order->delivery_type ?? $order->delivery_type }}
                                                    - {{ $order->delivery_name }}</span>
                                            @elseif($order->arrived)
                                                <br><span class="text-primary">وصلت</span> - <span
                                                    class="text-primary">{{ $order->arrived_at }}</span>
                                            @endif
                                        </td>
                                    @elseif ($order->status == 'delivered')
                                        <td class="text-success"> تم التسليم
                                            @if ($order->delivered)
                                                <br><span class="text-success">تم التسليم
                                                    {{ $order->delivered_at }}</span>
                                                <br> <span
                                                    class="text-primary">{{ $order->delivery_type ?? $order->delivery_type }}
                                                    - {{ $order->delivery_name }}</span>
                                            @elseif($order->arrived)
                                                <br><span class="text-primary">وصلت</span> - <span
                                                    class="text-primary">{{ $order->arrived_at }}</span>
                                            @endif
                                        </td>
                                    @endif

                                    <td class="">
                                        @if ($order->items->count() > 0)
                                            <button type="button" class="btn btn-light-info" data-bs-toggle="modal"
                                                data-bs-target="#exampleModalOrderItems{{ $order->id }}">المنتجات
                                                {{ $order->items->count() }} </button>
                                        @else
                                            -
                                        @endif
                                    </td>

                                    <td class="">{{ $order->qty }}
                                        @if ($order->bags_num)
                                            <br> عدد الطرود <span style="color: blue;">{{ $order->bags_num }}</span>
                                        @endif
                                        @if ($order->bags_qty)
                                            <br> الكمية في الطرود <span
                                                style="color: blue;">{{ $order->bags_qty }}</span>
                                        @endif
                                    </td>

                                    <td class="" style="color: green;">
                                        {{ $order->total_price }} {{ $order->currency_sign }}
                                        @if ($order->discount)
                                            <br>
                                            <span class="text-danger">تم التخفيض بقيمة
                                                {{ $order->discount }} DLY بسبب
                                                @if ($order->discount_reason == 'other')
                                                    <span
                                                        class="text-danger">{{ $order->discount_reason_note }}</span>
                                                @else
                                                    <span class="text-danger">{{ $order->discount_reason }}</span>
                                                @endif
                                            </span>
                                        @endif
                                    </td>

                                    <td class="text-warning"><span>
                                            {{ $order->total_price - $order->discount + $order->ship_price - $order->paid_price }}
                                            {{ $order->currency_sign }} </span>

                                        @if ($order->paids->count() > 0)
                                            <br>
                                            <span
                                                class="text-secondary">{{ $order->paids->last()->paymentMethod->name }}</span>
                                        @endif
                                    </td>


                                    <td class="">
                                        @if ($order->kilo)
                                            ({{ $order->kilo }} KG)
                                            <br>
                                        @endif
                                        @if ($order->ship_price)
                                            إجمالي سعر الشحن <span style="color: green;">{{ $order->ship_price }}
                                                {{ $order->currency_sign }}</span>
                                        @endif
                                        @if (!$order->ship_price && !$order->kilo)
                                            -
                                        @endif
                                    </td>


                                    <td class="">
                                        @if ($order->trip)
                                            <button type="button" class="btn btn-light-info" data-bs-toggle="modal"
                                                data-bs-target="#exampleModalTripDetails{{ $order->id }}">
                                                {{ $order->trip->code }} -
                                                {{ $order->trip_details }}
                                            </button>
                                        @elseif($order->trip_details)
                                            <button type="button" class="btn btn-light-info" data-bs-toggle="modal"
                                                data-bs-target="#exampleModalTripDetails{{ $order->id }}">
                                                {{ $order->trip_details }}
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-light-info" data-bs-toggle="modal"
                                                data-bs-target="#exampleModalTripDetails{{ $order->id }}">
                                                اضافة
                                            </button>
                                        @endif
                                        @if ($order->trip_status)
                                            <br>
                                            <span class="text-primary text-center">{{ $order->trip_status }}</span>
                                        @endif


                                        <div id="exampleModalOrderItems{{ $order->id }}" class="modal fade"
                                            tabindex="-1" role="dialog" aria-labelledby="exampleModalLiveLabel"
                                            aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-body text-center">
                                                        <div class="container">
                                                            <div>
                                                                <i class="fas fa-box-open text-primary fa-5x mb-3"></i>
                                                            </div>

                                                            <p style="font-size: 18px; word-wrap: break-word;">
                                                                منتجات الطلبية<span
                                                                    class="text-primary">{{ $order->code }}</span><br>
                                                                للزبون <span
                                                                    class="text-primary">{{ $order->customer->name }}</span>
                                                            </p>

                                                            <table class="table table-bordered">
                                                                <thead>
                                                                    <tr>
                                                                        <th>اسم المنتج</th>
                                                                        <th>الكمية</th>
                                                                        <th>السعر</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>

                                                                    @foreach ($order->items as $item)
                                                                        <tr>
                                                                            <td style="font-size: 16px;">
                                                                                {{ $item->name }}</td>
                                                                            <td style="font-size: 16px;">
                                                                                {{ $item->quantity }}</td>
                                                                            <td style="font-size: 16px;">
                                                                                {{ $item->price }}
                                                                                {{ $order->currency_sign }}</td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>


                                                            </table>


                                                            <div class="modal-footer d-flex justify-content-center">
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">إغـلاق</button>
                                                            </div>

                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div id="exampleModalTripDetails{{ $order->id }}" class="modal fade"
                                            tabindex="-1" role="dialog" aria-labelledby="exampleModalLiveLabel"
                                            aria-hidden="true">
                                            <div class="modal-dialog modal-dialog-centered" role="document">
                                                <div class="modal-content">
                                                    <div class="modal-body text-center">
                                                        <div class="container">
                                                            <div>
                                                                <i class="fas fa-ship text-primary fa-5x mb-3"></i>
                                                            </div>

                                                            <p style="font-size: 18px; word-wrap: break-word;">
                                                                بيانات رحلة الطلبية<span
                                                                    class="text-primary">{{ $order->code }}</span><br>
                                                                للزبون <span
                                                                    class="text-primary">{{ $order->customer->name }}</span>
                                                            </p>
                                                            <form
                                                                action="{{ route('order.trip_details', $order->id) }}"
                                                                method="post">
                                                                @csrf
                                                                <div class="col-md-12" style="text-align: right;">
                                                                    <div class="form-group">
                                                                        <label class="form-label"
                                                                            for="exampleInputName2332">بيانات
                                                                            الرحلة</label>
                                                                        <input type="text" name="trip_details"
                                                                            value="{{ $order->trip_details }}"
                                                                            class="form-control"
                                                                            id="exampleInputName2332"
                                                                            placeholder="بيانات الرحلة">
                                                                    </div>
                                                                </div>


                                                                <div class="col-md-12" style="text-align: right;">
                                                                    <div class="form-group">
                                                                        <label class="form-label"
                                                                            for="exampleInputName2332">حالة
                                                                            الرحلة</label>
                                                                        <select name="trip_status"
                                                                            class="form-control"
                                                                            id="exampleInputName2332">

                                                                            @foreach ($status as $status1)
                                                                                <option value="{{ $status1->name }}"
                                                                                    {{ $order->trip_status == $status1->name ? 'selected' : '' }}>
                                                                                    {{ $status1->name }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>
                                                                    </div>
                                                                </div>

                                                                <div class="col-md-12" style="text-align: right;">
                                                                    <div class="form-group">
                                                                        <label class="form-label"
                                                                            for="exampleInputName2332">ملاحظات
                                                                            الرحلة</label>
                                                                        <textarea type="text" name="trip_note" class="form-control" id="exampleInputName2332"
                                                                            placeholder="ملاحظات الرحلة">{{ $order->trip_note }}</textarea>
                                                                    </div>
                                                                </div>

                                                                <div
                                                                    class="modal-footer d-flex justify-content-center">
                                                                    <button type="submit" class="btn btn-primary">حفظ
                                                                        بيانات الرحلة</button>
                                                                    <button type="button" class="btn btn-secondary"
                                                                        data-bs-dismiss="modal">إغـلاق</button>
                                                                </div>
                                                            </form>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </td>


                                    <td>
                                        @if ($order->notes->count() > 0)
                                            <button type="button" class="btn btn-light-warning"
                                                data-bs-toggle="modal"
                                                data-bs-target="#exampleModalNotes{{ $order->id }}">
                                                {{ Str::words($order->notes->last()->note, 2, '...') }}
                                            </button>
                                        @elseif ($order->note)
                                            <button type="button" class="btn btn-light-warning"
                                                data-bs-toggle="modal"
                                                data-bs-target="#exampleModalNotes{{ $order->id }}">
                                                {{ Str::words($order->note, 2, '...') }}
                                            </button>
                                        @else
                                            <button type="button" class="btn btn-light-warning"
                                                data-bs-toggle="modal"
                                                data-bs-target="#exampleModalNotes{{ $order->id }}">ملاحظات</button>
                                        @endif

                                        <div id="exampleModalNotes{{ $order->id }}" class="modal fade"
                                            tabindex="-1"
                                            aria-labelledby="exampleModalNotesLabel{{ $order->id }}"
                                            aria-hidden="true">

                                            <div class="modal-dialog modal-dialog-centered modal-xl">
                                                <div class="modal-content">

                                                    {{-- =========================
                 MODAL HEADER
            ========================== --}}
                                                    <div class="modal-header">

                                                        <h5 class="modal-title"
                                                            id="exampleModalNotesLabel{{ $order->id }}">

                                                            ملاحظات الطلبية
                                                            <span class="text-primary">
                                                                {{ $order->code }}
                                                            </span>

                                                        </h5>

                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal" aria-label="Close">
                                                        </button>

                                                    </div>


                                                    {{-- =========================
                 MODAL BODY
            ========================== --}}
                                                    <div class="modal-body">

                                                        {{-- Order Information --}}
                                                        <div class="text-center mb-4">

                                                            <div>
                                                                <i
                                                                    class="fas fa-sticky-note text-primary fa-4x mb-3"></i>
                                                            </div>

                                                            <p style="font-size: 18px;">

                                                                ملاحظات الطلبية

                                                                <span class="text-primary">
                                                                    {{ $order->code }}
                                                                </span>

                                                                <br>

                                                                للزبون

                                                                <span class="text-primary">
                                                                    {{ $order->customer->name }}
                                                                </span>

                                                            </p>

                                                        </div>


                                                        {{-- =========================
                     EXISTING NOTES
                ========================== --}}
                                                        <div class="mb-4">

                                                            <div
                                                                class="d-flex justify-content-between align-items-center mb-3">

                                                                <h5 class="mb-0">
                                                                    <i class="fas fa-history text-primary"></i>
                                                                    سجل الملاحظات
                                                                </h5>

                                                            </div>

                                                            @if ($order->note)
                                                                <div class="card mb-3 shadow-sm">
                                                                    <div class="card-body">
                                                                        <div class="row">
                                                                            {{-- Note --}}
                                                                            <div class="col-md-9"
                                                                                style="text-align: right;">
                                                                                <h6 class="mb-2">
                                                                                    <i
                                                                                        class="fas fa-comment-alt text-primary"></i>
                                                                                    الملاحظة
                                                                                </h6>
                                                                                <div
                                                                                    style="
                                                font-size: 16px;
                                                white-space: pre-wrap;
                                                word-break: break-word;
                                            ">
                                                                                    {{ $order->note }}
                                                                                </div>
                                                                            </div>
                                                                            {{-- Date / Time --}}
                                                                            <div
                                                                                class="col-md-3 text-md-end text-center">
                                                                                <small class="text-muted">
                                                                                    <i class="far fa-calendar-alt"></i>
                                                                                    {{ $order->updated_at->format('Y-m-d') }}
                                                                                    <br>
                                                                                    <i class="far fa-clock"></i>
                                                                                    {{ $order->updated_at->format('H:i') }}
                                                                                </small>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif

                                                            @if ($order->notes && $order->notes->count() > 0)
                                                                @foreach ($order->notes->sortByDesc('created_at') as $orderNote)
                                                                    <div class="card mb-3 shadow-sm">
                                                                        <div class="card-body">
                                                                            <div class="row">
                                                                                {{-- Note --}}
                                                                                <div class="col-md-9"
                                                                                    style="text-align: right;">
                                                                                    <h6 class="mb-2">
                                                                                        <i
                                                                                            class="fas fa-comment-alt text-primary"></i>
                                                                                        الملاحظة
                                                                                    </h6>
                                                                                    <div
                                                                                        style="
                                                font-size: 16px;
                                                white-space: pre-wrap;
                                                word-break: break-word;
                                            ">
                                                                                        {{ $orderNote->note }}
                                                                                    </div>
                                                                                </div>
                                                                                {{-- Date / Time --}}
                                                                                <div
                                                                                    class="col-md-3 text-md-end text-center">

                                                                                    <small class="text-muted">
                                                                                        <i class="fas fa-user"></i>
                                                                                        {{ $orderNote->user->name ?? 'غير معروف' }}
                                                                                        <br>

                                                                                        <i
                                                                                            class="far fa-calendar-alt"></i>
                                                                                        {{ $orderNote->created_at->format('Y-m-d') }}
                                                                                        <br>

                                                                                        <i class="far fa-clock"></i>
                                                                                        {{ $orderNote->created_at->format('H:i') }}
                                                                                    </small>

                                                                                    <div class="mt-2">
                                                                                        <form
                                                                                            action="{{ route('order.notes.destroy', $orderNote->id) }}"
                                                                                            method="POST"
                                                                                            onsubmit="return confirm('هل أنت متأكد من حذف هذه الملاحظة؟');"
                                                                                            style="display: inline-block;">

                                                                                            @csrf
                                                                                            @method('DELETE')

                                                                                            <button type="submit"
                                                                                                class="btn btn-sm btn-danger">
                                                                                                <i
                                                                                                    class="fas fa-trash"></i>
                                                                                                حذف
                                                                                            </button>
                                                                                        </form>
                                                                                    </div>

                                                                                </div>
                                                                            </div>
                                                                            {{-- =========================
                                         IMAGE BUTTON
                                    ========================== --}}
                                                                            @if ($orderNote->image)
                                                                                <div class="mt-3"
                                                                                    style="text-align: right;">

                                                                                    <button type="button"
                                                                                        class="btn btn-outline-primary"
                                                                                        data-bs-toggle="modal"
                                                                                        data-bs-target="#globalNoteImageModal"
                                                                                        data-image="{{ asset('uploads/order_notes/' . $orderNote->image) }}">

                                                                                        <i class="fas fa-image"></i>
                                                                                        عرض الصورة

                                                                                    </button>

                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    </div>
                                                                @endforeach
                                                            @elseif($order->note === null)
                                                                <div class="alert alert-info text-center">

                                                                    <i class="fas fa-info-circle"></i>

                                                                    لا توجد ملاحظات لهذه الطلبية.

                                                                </div>
                                                            @endif

                                                        </div>


                                                        <hr>


                                                        {{-- =========================
                     ADD NEW NOTE
                ========================== --}}
                                                        <div class="mt-4">

                                                            <h5 class="mb-3" style="text-align: right;">

                                                                <i class="fas fa-plus-circle text-primary"></i>

                                                                إضافة ملاحظة جديدة

                                                            </h5>


                                                            <form action="{{ route('order.changeNote', $order->id) }}"
                                                                method="POST" enctype="multipart/form-data">

                                                                @csrf


                                                                {{-- Note --}}
                                                                <div class="col-md-12" style="text-align: right;">

                                                                    <div class="form-group mb-3">

                                                                        <label class="form-label"
                                                                            for="note{{ $order->id }}">

                                                                            الملاحظات

                                                                        </label>

                                                                        <textarea name="note" class="form-control" id="note{{ $order->id }}" rows="4"
                                                                            placeholder="ملاحظات (إختياري)"></textarea>

                                                                    </div>

                                                                </div>


                                                                {{-- Image --}}
                                                                <div class="col-md-12" style="text-align: right;">

                                                                    <div class="form-group mb-3">

                                                                        <label class="form-label"
                                                                            for="order_image{{ $order->id }}">

                                                                            إرفاق صورة إختياري

                                                                        </label>

                                                                        <input type="file" name="image"
                                                                            id="order_image{{ $order->id }}"
                                                                            class="form-control" accept="image/*">

                                                                        <small class="form-text text-muted">

                                                                            يمكنك رفع أي نوع من ملفات الصور.

                                                                        </small>

                                                                    </div>

                                                                </div>


                                                                {{-- Delivered --}}
                                                                <div class="col-md-12" style="text-align: right;">

                                                                    <div class="form-group mb-3">

                                                                        <div class="form-check">

                                                                            <input class="form-check-input"
                                                                                type="checkbox" name="delivered"
                                                                                value="1"
                                                                                id="delivered{{ $order->id }}"
                                                                                @if ($order->delivered) checked @endif>

                                                                            <label class="form-check-label"
                                                                                for="delivered{{ $order->id }}">

                                                                                تسليم الطلبية إلى الزبون

                                                                            </label>

                                                                        </div>

                                                                    </div>

                                                                </div>


                                                                {{-- =========================
                             FORM BUTTONS
                        ========================== --}}
                                                                <div
                                                                    class="modal-footer d-flex justify-content-center">

                                                                    <button type="submit" class="btn btn-primary">

                                                                        <i class="fas fa-save"></i>

                                                                        حفظ الملاحظة

                                                                    </button>


                                                                    <button type="button" class="btn btn-secondary"
                                                                        data-bs-dismiss="modal">

                                                                        <i class="fas fa-times"></i>

                                                                        إغلاق

                                                                    </button>

                                                                </div>

                                                            </form>

                                                        </div>

                                                    </div>

                                                </div>

                                            </div>

                                        </div>




                                        {{-- =====================================================
     GLOBAL IMAGE MODAL
===================================================== --}}

                                        <div class="modal fade" id="globalNoteImageModal" tabindex="-1"
                                            aria-labelledby="globalNoteImageModalLabel" aria-hidden="true">

                                            <div class="modal-dialog modal-dialog-centered modal-lg">

                                                <div class="modal-content">

                                                    <div class="modal-header">

                                                        <h5 class="modal-title" id="globalNoteImageModalLabel">

                                                            صورة الملاحظة

                                                        </h5>

                                                        <button type="button" class="btn-close"
                                                            data-bs-dismiss="modal" aria-label="Close">
                                                        </button>

                                                    </div>


                                                    <div class="modal-body text-center">

                                                        <img id="globalNoteImage" src="" alt="Note Image"
                                                            class="img-fluid"
                                                            style="
                        max-height: 75vh;
                        max-width: 100%;
                        object-fit: contain;
                    ">

                                                    </div>


                                                    <div class="modal-footer justify-content-center">

                                                        <button type="button" class="btn btn-secondary"
                                                            data-bs-dismiss="modal">

                                                            إغلاق

                                                        </button>

                                                    </div>

                                                </div>

                                            </div>

                                        </div>

                                    </td>



                                    <td>

                                        <div class="btn-group mb-2 me-2">
                                            <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                                data-bs-toggle="dropdown" aria-haspopup="true"
                                                aria-expanded="false">إجراءات
                                                {{ $order->customer->code }}</button>
                                            <div class="dropdown-menu">
                                                @can('order-edit')
                                                    <a class="dropdown-item"
                                                        href="{{ route('order.edit', $order->id) }}">تعديل</a>

                                                    @if ($order->paids->count() > 0)
                                                        <a class="dropdown-item"
                                                            href="{{ route('order.paids.history', $order->id) }}">سجل
                                                            التسديدات</a>
                                                    @endif

                                                    @if (
                                                        $order->status == 'unpaid' ||
                                                            ($order->status == 'paid' && $order->ship_price > 0) ||
                                                            ($order->status == 'paid_full' &&
                                                                $order->total_price + $order->ship_price - $order->discount - $order->paid_price > 0))
                                                        <a class="dropdown-item" href="#" data-bs-toggle="modal"
                                                            data-bs-target="#exampleModalLivePatRest{{ $order->id }}">دفع
                                                            الباقي</a>
                                                    @endif
                                                    @if ($order->delivered == false)
                                                        <a class="dropdown-item" href="#" data-bs-toggle="modal"
                                                            data-bs-target="#exampleModalLivedelivered{{ $order->id }}">تسليم</a>
                                                    @endif

                                                    @if ($order->trip && $order->trip->arrived == true && $order->arrived == false)
                                                        <a class="dropdown-item" href="#" data-bs-toggle="modal"
                                                            data-bs-target="#exampleModalLiveArrived{{ $order->id }}">وصلت</a>
                                                    @endif
                                                @endcan
                                                @can('order-receive')
                                                    @if ($order->delivered == false && $order->status != 'canceld')
                                                        <a class="dropdown-item text-success" href="{{ route('order.receive.form', $order->id) }}">{{ $order->received_at ? 'تعديل الاستلام' : 'استلام البضاعة' }}</a>
                                                    @endif
                                                @endcan
                                                @can('trip-purchase-list')
                                                    @if ($order->tripPurchase)
                                                        <a class="dropdown-item" href="{{ route('finance.trips.show', $order->tripPurchase->id) }}">رحلة الشراء {{ $order->trip_details }}</a>
                                                    @endif
                                                @endcan
                                                @can('order-edit')

                                                    <a class="dropdown-item" href="#" data-bs-toggle="modal"
                                                        data-bs-target="#exampleModalLiveDelete{{ $order->id }}">حذف</a>
                                                @endcan
                                            </div>
                                        </div>

                                        @can('order-edit')
                                            @if (
                                                $order->status == 'unpaid' ||
                                                    ($order->status == 'paid' && $order->ship_price > 0) ||
                                                    ($order->status == 'paid_full' &&
                                                        $order->total_price + $order->ship_price - $order->discount - $order->paid_price > 0))
                                                <div id="exampleModalLivePatRest{{ $order->id }}" class="modal fade"
                                                    tabindex="-1" role="dialog" aria-labelledby="exampleModalLiveLabel"
                                                    aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-body text-center">
                                                                <div class="container">
                                                                    <div>
                                                                        <i
                                                                            class="ti ti-alert-circle text-primary fa-5x mb-3"></i>
                                                                    </div>
                                                                    @if ($order->ship_price)
                                                                        <p style="font-size: 18px; word-wrap: break-word;">
                                                                            اجمالي القيمة المتبقية من
                                                                            الطلب <span
                                                                                class="text-primary">{{ $order->code }}</span><br>
                                                                            للزبون <span
                                                                                class="text-primary">{{ $order->customer->name }}</span>
                                                                            هي <span
                                                                                class="text-success">{{ $order->total_price - $order->discount + $order->ship_price - $order->paid_price }}
                                                                                {{ $order->currency_sign }}</span>
                                                                        </p>
                                                                    @else
                                                                        <p style="font-size: 18px; word-wrap: break-word;">
                                                                            اجمالي القيمة المتبقية من
                                                                            الطلب <span
                                                                                class="text-primary">{{ $order->code }}</span><br>
                                                                            للزبون <span
                                                                                class="text-primary">{{ $order->customer->name }}</span>
                                                                            هي <span
                                                                                class="text-success">{{ $order->total_price - $order->discount - $order->paid_price }}
                                                                                {{ $order->currency_sign }}</span>
                                                                        </p>
                                                                        <br><span class="text-warning">ملاحطة
                                                                            :: باقي القيمة بدون سعر
                                                                            الشحن</span>
                                                                    @endif

                                                                    @php
                                                                        $remaining = $order->ship_price
                                                                            ? $order->total_price +
                                                                                $order->ship_price -
                                                                                $order->paid_price -
                                                                                $order->discount
                                                                            : $order->total_price -
                                                                                $order->paid_price -
                                                                                $order->discount;
                                                                    @endphp

                                                                    <form
                                                                        action="{{ route('order.payRest', $order->id) }}"
                                                                        method="post">
                                                                        @csrf
                                                                        <div class="col-md-12">
                                                                            <div class="form-group">
                                                                                <label class="form-label"
                                                                                    for="exampleInputName232">القيمة
                                                                                    المدفوعة</label>
                                                                                @if ($order->ship_price)
                                                                                    <input type="number" step="any"
                                                                                        min="1"
                                                                                        max="{{ $order->total_price - $order->discount + $order->ship_price - $order->paid_price }}"
                                                                                        name="paid_price"
                                                                                        class="form-control"
                                                                                        id="exampleInputName232"required
                                                                                        placeholder="القيمة المدفوعة"
                                                                                        oninvalid="this.setCustomValidity('الرجاء ادخال القيمة المدفوعة')"
                                                                                        oninput="this.setCustomValidity('')">
                                                                                @else
                                                                                    <input type="number" step="any"
                                                                                        min="1"
                                                                                        max="{{ $order->total_price - $order->discount - $order->paid_price }}"
                                                                                        name="paid_price"
                                                                                        class="form-control"
                                                                                        id="exampleInputName232"required
                                                                                        placeholder="القيمة المدفوعة"
                                                                                        oninvalid="this.setCustomValidity('الرجاء ادخال القيمة المدفوعة')"
                                                                                        oninput="this.setCustomValidity('')">
                                                                                @endif
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-12">
                                                                            <div class="form-group">
                                                                                <label class="form-label"
                                                                                    for="payment_id">طريقة الدفع </label>
                                                                                <select name="payment_id" required
                                                                                    id="payment_id" class="form-control">
                                                                                    <option>-- اختر طريقة الدفع --</option>
                                                                                    @foreach ($payments as $index => $payment)
                                                                                        @if ($payment->id == 1)
                                                                                            <option
                                                                                                value="{{ $payment->id }}"
                                                                                                selected>
                                                                                                {{ $payment->name }}
                                                                                            </option>
                                                                                        @else
                                                                                            <option
                                                                                                value="{{ $payment->id }}">
                                                                                                {{ $payment->name }}
                                                                                            </option>
                                                                                        @endif
                                                                                    @endforeach

                                                                                </select>
                                                                            </div>
                                                                        </div>


                                                                        @if ($order->discount)
                                                                            <div class="col-md-12">
                                                                                <div class="form-group">
                                                                                    <div
                                                                                        class="form-check d-flex justify-content-center align-items-center gap-4">
                                                                                        <p class="">
                                                                                            تم التخفيض بقيمة <span
                                                                                                class="text-success">{{ $order->discount }}</span>
                                                                                            DLY بسبب
                                                                                            @if ($order->discount_reason == 'other')
                                                                                                <span
                                                                                                    class="text-danger">{{ $order->discount_reason_note }}</span>
                                                                                            @else
                                                                                                <span
                                                                                                    class="text-danger">{{ $order->discount_reason }}</span>
                                                                                            @endif
                                                                                        </p>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        @else
                                                                            <div class="col-md-12">
                                                                                <div class="form-group">
                                                                                    <div
                                                                                        class="form-check d-flex justify-content-center align-items-center gap-4">
                                                                                        <input class="form-check-input"
                                                                                            type="checkbox"
                                                                                            id="hasDiscount{{ $order->id }}"
                                                                                            onchange="toggleDiscount({{ $order->id }})">
                                                                                        <label class="form-check-label"
                                                                                            style="padding-right: 8px;"
                                                                                            for="hasDiscount{{ $order->id }}">
                                                                                            إضافة تخفيض
                                                                                        </label>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        @endif


                                                                        <!-- ✅ Discount section (hidden until checked) -->
                                                                        <div id="discountWrap{{ $order->id }}"
                                                                            style="display:none">
                                                                            <div class="col-md-12">
                                                                                <div class="form-group">
                                                                                    <label class="form-label"
                                                                                        for="discountAmount{{ $order->id }}">قيمة
                                                                                        التخفيض *</label>
                                                                                    <input type="number" step="any"
                                                                                        min="0"
                                                                                        max="{{ $remaining }}"
                                                                                        name="discount"
                                                                                        class="form-control"
                                                                                        id="discountAmount{{ $order->id }}"
                                                                                        placeholder="قيمة التخفيض"
                                                                                        disabled>
                                                                                </div>
                                                                            </div>

                                                                            <div class="col-md-12">
                                                                                <div class="form-group">
                                                                                    <label class="form-label"
                                                                                        for="discountReason{{ $order->id }}">سبب
                                                                                        التخفيض *</label>
                                                                                    <select class="form-select"
                                                                                        name="discount_reason"
                                                                                        id="discountReason{{ $order->id }}"
                                                                                        onchange="onReasonChange({{ $order->id }})"
                                                                                        disabled>
                                                                                        <option selected disabled>اختر سبب
                                                                                            التخفيض
                                                                                        </option>
                                                                                        <option value="توصيل">توصيل
                                                                                        </option>
                                                                                        <option value="قطع ناقصة">قطع ناقصة
                                                                                        </option>
                                                                                        <option value="قطع تالفة">قطع تالفة
                                                                                        </option>
                                                                                        <option value="تأخير في الطلبية">
                                                                                            تأخير في
                                                                                            الطلبية</option>
                                                                                        <option value="other">أخرى
                                                                                        </option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>

                                                                            <!-- ✅ "Other" note (hidden until 'other' is selected) -->
                                                                            <div class="col-md-12"
                                                                                id="discountOtherWrap{{ $order->id }}"
                                                                                style="display:none">
                                                                                <div class="form-group">
                                                                                    <label class="form-label"
                                                                                        for="discountReasonNote{{ $order->id }}">سبب
                                                                                        التخفيض (تفاصيل) *</label>
                                                                                    <textarea name="discount_reason_note" class="form-control" id="discountReasonNote{{ $order->id }}"
                                                                                        placeholder="اكتب سبب التخفيض بالتفصيل" disabled></textarea>
                                                                                </div>
                                                                            </div>
                                                                        </div>


                                                                        <div
                                                                            class="modal-footer d-flex justify-content-center">
                                                                            <button type="submit"
                                                                                class="btn btn-primary">نـعـم</button>
                                                                            <button type="button"
                                                                                class="btn btn-secondary"
                                                                                data-bs-dismiss="modal">إغـلاق</button>
                                                                        </div>
                                                                    </form>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            @if ($order->delivered == false)
                                                <div id="exampleModalLivedelivered{{ $order->id }}"
                                                    class="modal fade" tabindex="-1" role="dialog"
                                                    aria-labelledby="exampleModalLiveLabel" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-body text-center">
                                                                <div class="container">
                                                                    <div>
                                                                        <i
                                                                            class="ti ti-alert-circle text-success fa-5x mb-3"></i>
                                                                    </div>

                                                                    <p style="font-size: 18px; word-wrap: break-word;">
                                                                        تسليم بضاعة الطلب <span
                                                                            class="text-success">{{ $order->code }}</span><br>
                                                                        للزبون <span
                                                                            class="text-success">{{ $order->customer->name }}</span>
                                                                    </p>
                                                                    <form
                                                                        action="{{ route('order.delivered', $order->id) }}"
                                                                        method="post">
                                                                        @csrf
                                                                        <div class="col-md-12">
                                                                            <div class="form-group">
                                                                                <label class="form-label"
                                                                                    for="exampleInputName232">عدد
                                                                                    الطرود</label>
                                                                                <input type="number"
                                                                                    value="{{ $order->bags_num }}"
                                                                                    name="bags_num" class="form-control"
                                                                                    id="exampleInputName232" required
                                                                                    placeholder="عدد الطرود"
                                                                                    oninvalid="this.setCustomValidity('الرجاء ادخال عدد الطرود')"
                                                                                    oninput="this.setCustomValidity('')">
                                                                            </div>
                                                                        </div>
                                                                        <div class="col-md-12">
                                                                            <div class="form-group">
                                                                                <label class="form-label"
                                                                                    for="exampleInputName232">الكمية
                                                                                    في الطرود</label>
                                                                                <input type="number"
                                                                                    value="{{ $order->bags_qty }}"
                                                                                    name="bags_qty" class="form-control"
                                                                                    id="exampleInputName232" required
                                                                                    placeholder="الكمية في الطرود"
                                                                                    oninvalid="this.setCustomValidity('الرجاء ادخال الكمية في الطرود')"
                                                                                    oninput="this.setCustomValidity('')">
                                                                            </div>
                                                                        </div>

                                                                        @if ($order->ship_price)
                                                                            <p
                                                                                style="font-size: 18px; word-wrap: break-word;">
                                                                                اجمالي القيمة المتبقية
                                                                                هي <span
                                                                                    class="text-success">{{ $order->total_price - $order->discount + $order->ship_price - $order->paid_price }}
                                                                                    {{ $order->currency_sign }}</span>
                                                                            </p>
                                                                        @else
                                                                            <p
                                                                                style="font-size: 18px; word-wrap: break-word;">
                                                                                اجمالي القيمة المتبقية
                                                                                هي <span
                                                                                    class="text-success">{{ $order->total_price - $order->discount - $order->paid_price }}
                                                                                    {{ $order->currency_sign }}</span>
                                                                                <br><span class="text-warning">ملاحطة
                                                                                    :: باقي القيمة بدون
                                                                                    سعر
                                                                                    الشحن</span>
                                                                            </p>
                                                                        @endif
                                                                        @if (
                                                                            $order->status == 'unpaid' ||
                                                                                $order->status == 'paid' ||
                                                                                ($order->status == 'paid_full' &&
                                                                                    $order->total_price + $order->ship_price - $order->discount - $order->paid_price > 0))
                                                                            <label class="form-label"
                                                                                for="exampleInputName232">القيمة
                                                                                المدفوعة</label>
                                                                            @if ($order->ship_price)
                                                                                <input type="number" step="any"
                                                                                    min="1"
                                                                                    placeholder="القيمة المدفوعة"
                                                                                    max="{{ $order->total_price - $order->discount + $order->ship_price - $order->paid_price }}"
                                                                                    name="paid_price" class="form-control"
                                                                                    id="exampleInputName232">
                                                                            @else
                                                                                <input type="number" step="any"
                                                                                    min="1"
                                                                                    placeholder="القيمة المدفوعة"
                                                                                    max="{{ $order->total_price - $order->discount - $order->paid_price }}"
                                                                                    name="paid_price" class="form-control"
                                                                                    id="exampleInputName232">
                                                                            @endif

                                                                            <div class="col-md-12">
                                                                                <div class="form-group">
                                                                                    <label class="form-label"
                                                                                        for="payment_id">طريقة الدفع
                                                                                    </label>
                                                                                    <select name="payment_id" required
                                                                                        id="payment_id"
                                                                                        class="form-control">
                                                                                        <option>-- اختر طريقة الدفع --
                                                                                        </option>
                                                                                        @foreach ($payments as $index => $payment)
                                                                                            @if ($payment->id == 1)
                                                                                                <option
                                                                                                    value="{{ $payment->id }}"
                                                                                                    selected>
                                                                                                    {{ $payment->name }}
                                                                                                </option>
                                                                                            @else
                                                                                                <option
                                                                                                    value="{{ $payment->id }}">
                                                                                                    {{ $payment->name }}
                                                                                                </option>
                                                                                            @endif
                                                                                        @endforeach

                                                                                    </select>
                                                                                </div>
                                                                            </div>
                                                                        @endif


                                                                        {{-- ✅ Select for delivery_type --}}
                                                                        <div class="col-md-12 mt-3">
                                                                            <div class="form-group">
                                                                                <label class="form-label">طريقة
                                                                                    التوصيل</label>
                                                                                <select name="delivery_type"
                                                                                    id="deliveryType{{ $order->id }}"
                                                                                    class="form-select">
                                                                                    <option value="">
                                                                                        — اختر طريقة
                                                                                        التوصيل —
                                                                                    </option>
                                                                                    <option value="توصيل شخصي"
                                                                                        {{ $order->delivery_type == 'توصيل شخصي' ? 'selected' : '' }}>
                                                                                        توصيل شخصي
                                                                                    </option>
                                                                                    <option value="توصيل عن طريق مندوب"
                                                                                        {{ $order->delivery_type == 'توصيل عن طريق مندوب' ? 'selected' : '' }}>
                                                                                        توصيل عن طريق
                                                                                        مندوب</option>
                                                                                    <option value="توصيل عن طريق مكتب"
                                                                                        {{ $order->delivery_type == 'توصيل عن طريق مكتب' ? 'selected' : '' }}>
                                                                                        توصيل عن طريق
                                                                                        مكتب</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>

                                                                        {{-- ✅ Hidden input for delivery_name --}}
                                                                        <div class="col-md-12 mt-3"
                                                                            id="deliveryNameBox{{ $order->id }}"
                                                                            style="display: none;">
                                                                            <div class="form-group">
                                                                                <label class="form-label">اسم
                                                                                    المندوب أو
                                                                                    المكتب</label>
                                                                                <input type="text" name="delivery_name"
                                                                                    value="{{ $order->delivery_name }}"
                                                                                    class="form-control"
                                                                                    placeholder="ادخل اسم المندوب أو المكتب">
                                                                            </div>
                                                                        </div>

                                                                        {{-- ✅ Script to toggle visibility --}}
                                                                        <script>
                                                                            document.addEventListener('DOMContentLoaded', function() {
                                                                                const select = document.getElementById('deliveryType{{ $order->id }}');
                                                                                const nameBox = document.getElementById('deliveryNameBox{{ $order->id }}');

                                                                                function toggleNameBox() {
                                                                                    if (select.value === 'توصيل عن طريق مندوب' || select.value === 'توصيل عن طريق مكتب') {
                                                                                        nameBox.style.display = 'block';
                                                                                    } else {
                                                                                        nameBox.style.display = 'none';
                                                                                    }
                                                                                }

                                                                                // Initialize state when modal opens
                                                                                select.addEventListener('change', toggleNameBox);
                                                                                toggleNameBox();
                                                                            });
                                                                        </script>



                                                                        @php
                                                                            $remaining = $order->ship_price
                                                                                ? $order->total_price +
                                                                                    $order->ship_price -
                                                                                    $order->paid_price -
                                                                                    $order->discount
                                                                                : $order->total_price -
                                                                                    $order->paid_price -
                                                                                    $order->discount;
                                                                        @endphp

                                                                        @if ($order->discount)
                                                                            <div class="col-md-12">
                                                                                <div class="form-group">
                                                                                    <div
                                                                                        class="form-check d-flex justify-content-center align-items-center gap-4">
                                                                                        <p class="">
                                                                                            تم التخفيض بقيمة <span
                                                                                                class="text-success">{{ $order->discount }}</span>
                                                                                            DLY بسبب
                                                                                            @if ($order->discount_reason == 'other')
                                                                                                <span
                                                                                                    class="text-danger">{{ $order->discount_reason_note }}</span>
                                                                                            @else
                                                                                                <span
                                                                                                    class="text-danger">{{ $order->discount_reason }}</span>
                                                                                            @endif
                                                                                        </p>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        @else
                                                                            <div class="col-md-12">
                                                                                <div class="form-group">
                                                                                    <div
                                                                                        class="form-check d-flex justify-content-center align-items-center gap-4">
                                                                                        <input class="form-check-input"
                                                                                            type="checkbox"
                                                                                            id="hasDiscount2{{ $order->id }}"
                                                                                            onchange="toggleDiscount2({{ $order->id }})">
                                                                                        <label class="form-check-label"
                                                                                            style="padding-right: 8px;"
                                                                                            for="hasDiscount2{{ $order->id }}">
                                                                                            إضافة تخفيض
                                                                                        </label>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        @endif


                                                                        <!-- ✅ Discount section (hidden until checked) -->
                                                                        <div id="discountWrap2{{ $order->id }}"
                                                                            style="display:none">
                                                                            <div class="col-md-12">
                                                                                <div class="form-group">
                                                                                    <label class="form-label"
                                                                                        for="discountAmount2{{ $order->id }}">قيمة
                                                                                        التخفيض *</label>
                                                                                    <input type="number" step="any"
                                                                                        min="0"
                                                                                        max="{{ $remaining }}"
                                                                                        name="discount"
                                                                                        class="form-control"
                                                                                        id="discountAmount2{{ $order->id }}"
                                                                                        placeholder="قيمة التخفيض"
                                                                                        disabled>
                                                                                </div>
                                                                            </div>

                                                                            <div class="col-md-12">
                                                                                <div class="form-group">
                                                                                    <label class="form-label"
                                                                                        for="discountReason2{{ $order->id }}">سبب
                                                                                        التخفيض *</label>
                                                                                    <select class="form-select"
                                                                                        name="discount_reason"
                                                                                        id="discountReason2{{ $order->id }}"
                                                                                        onchange="onReasonChange2({{ $order->id }})">
                                                                                        <option selected value=""
                                                                                            disabled>اختر سبب التخفيض
                                                                                        </option>
                                                                                        <option value="توصيل">توصيل
                                                                                        </option>
                                                                                        <option value="قطع ناقصة">قطع ناقصة
                                                                                        </option>
                                                                                        <option value="قطع تالفة">قطع تالفة
                                                                                        </option>
                                                                                        <option value="تأخير في الطلبية">
                                                                                            تأخير في
                                                                                            الطلبية</option>
                                                                                        <option value="other">أخرى
                                                                                        </option>
                                                                                    </select>
                                                                                </div>
                                                                            </div>

                                                                            <!-- ✅ "Other" note (hidden until 'other' is selected) -->
                                                                            <div class="col-md-12"
                                                                                id="discountOtherWrap2{{ $order->id }}"
                                                                                style="display:none">
                                                                                <div class="form-group">
                                                                                    <label class="form-label"
                                                                                        for="discountReasonNote2{{ $order->id }}">سبب
                                                                                        التخفيض (تفاصيل) *</label>
                                                                                    <textarea name="discount_reason_note" class="form-control" id="discountReasonNote2{{ $order->id }}"
                                                                                        placeholder="اكتب سبب التخفيض بالتفصيل" disabled></textarea>
                                                                                </div>
                                                                            </div>
                                                                        </div>





                                                                        <div
                                                                            class="modal-footer d-flex justify-content-center">
                                                                            <button type="submit"
                                                                                class="btn btn-success">نـعـم</button>
                                                                            <button type="button"
                                                                                class="btn btn-secondary"
                                                                                data-bs-dismiss="modal">إغـلاق</button>
                                                                        </div>

                                                                    </form>
                                                                </div>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif

                                            @if ($order->trip && $order->trip->arrived == true && $order->arrived == false)
                                                <div id="exampleModalLiveArrived{{ $order->id }}" class="modal fade"
                                                    tabindex="-1" role="dialog" aria-labelledby="exampleModalLiveLabel"
                                                    aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-body text-center">
                                                                <div>
                                                                    <i
                                                                        class="ti ti-alert-circle text-primary fa-5x mb-3"></i>
                                                                </div>
                                                                <p style="font-size: 18px;">
                                                                    هل انت متأكد من وصول الطلبية <span
                                                                        class="text-primary">{{ $order->code }}</span>
                                                                    للزبون <span
                                                                        class="text-primary">{{ $order->customer->name }}</span>
                                                                    ؟</p>
                                                                <form action="{{ route('order.arrived', $order->id) }}"
                                                                    method="post">
                                                                    @csrf
                                                                    @method('DELETE')
                                                            </div>
                                                            <div class="modal-footer d-flex justify-content-center">
                                                                <button type="submit"
                                                                    class="btn btn-primary">نـعـم</button>
                                                                </form>
                                                                <button type="button" class="btn btn-secondary"
                                                                    data-bs-dismiss="modal">إغـلاق</button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endif


                                            <div id="exampleModalLiveDelete{{ $order->id }}" class="modal fade"
                                                tabindex="-1" role="dialog" aria-labelledby="exampleModalLiveLabel"
                                                aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-body text-center">
                                                            <div>
                                                                <i class="ti ti-alert-circle text-danger fa-5x mb-3"></i>
                                                            </div>
                                                            <p style="font-size: 18px;">
                                                                هل انت متأكد من حذف الطلبية <span
                                                                    class="text-danger">{{ $order->code }}</span>
                                                                <br>
                                                                للزبون <span
                                                                    class="text-danger">{{ $order->customer->name }}</span>
                                                                ؟
                                                            </p>
                                                            <form
                                                                action="{{ route('order_delete.destroy', $order->id) }}"
                                                                method="post">
                                                                @csrf
                                                                @method('DELETE')
                                                        </div>
                                                        <div class="modal-footer d-flex justify-content-center">
                                                            <button type="submit" class="btn btn-danger">نـعـم</button>
                                                            </form>
                                                            <button type="button" class="btn btn-secondary"
                                                                data-bs-dismiss="modal">إغـلاق</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endcan

                                    </td>
                                </tr>
                            @endforeach
                        </tbody>

                        <tfoot>
                            <tr>
                                <th rowspan="1" colspan="1">#</th>
                                <th rowspan="1" colspan="1">كود الطلب</th>
                                <th rowspan="1" colspan="1">الزبون</th>
                                <th rowspan="1" colspan="1">نوع الطرد</th>
                                <th rowspan="1" colspan="1">حالة الطلب</th>
                                <th rowspan="1" colspan="1">المنتجات</th>
                                <th rowspan="1" colspan="1">كمية القطع</th>
                                <th rowspan="1" colspan="1">اجمالي سعر البضاعة</th>
                                <th rowspan="1" colspan="1">القيمة المتبقية</th>
                                <th rowspan="1" colspan="1">وزن البضاعة وسعر الشحن</th>
                                <th rowspan="1" colspan="1">الرحلة</th>
                                <th rowspan="1" colspan="1">ملاحظات</th>
                                <th rowspan="1" colspan="1">الاجراءات</th>
                            </tr>
                        </tfoot>
                    </table>
                    @if (isset($query))
                        <span id="paginate">{{ $orders->appends($query)->links('pagination::bootstrap-4') }}</span>
                    @else
                        <span id="paginate">{{ $orders->links('pagination::bootstrap-4') }}</span>
                    @endif
                </div>
            </div>

        </div>

    </div>
</div>




<script>
    function toggleDeliverySelect(id) {
        const deliveryOption = document.querySelector(`#exampleModalNotes${id} input[name="recive"]:checked`).value;
        const deliverySelectDiv = document.getElementById('deliverySelectDiv' + id);
        if (deliveryOption === 'delivery') {
            deliverySelectDiv.style.display = 'block';
        } else {
            deliverySelectDiv.style.display = 'none';
        }
    }

    function toggleDeliverySelect2(id) {
        const deliveryOption = document.querySelector(`#exampleModalLivedelivered${id} input[name="recive2"]:checked`)
            .value;
        const deliverySelectDiv = document.getElementById('deliverySelectDiv2' + id);
        if (deliveryOption === 'delivery') {
            deliverySelectDiv.style.display = 'block';
        } else {
            deliverySelectDiv.style.display = 'none';
        }
    }

    function toggleDeliverySelect3(id) {
        const deliveryOption = document.querySelector(`#exampleModalLivePatRest${id} input[name="recive3"]:checked`)
            .value;
        const deliverySelectDiv = document.getElementById('deliverySelectDiv22' + id);
        if (deliveryOption === 'delivery') {
            deliverySelectDiv.style.display = 'block';
        } else {
            deliverySelectDiv.style.display = 'none';
        }
    }


    function toggleDiscount(id) {
        const checked = document.getElementById('hasDiscount' + id).checked;
        const wrap = document.getElementById('discountWrap' + id);

        const amount = document.getElementById('discountAmount' + id);
        const reason = document.getElementById('discountReason' + id);
        const otherWrap = document.getElementById('discountOtherWrap' + id);
        const note = document.getElementById('discountReasonNote' + id);

        wrap.style.display = checked ? '' : 'none';

        amount.disabled = !checked;
        reason.disabled = !checked;
        amount.required = checked;
        reason.required = checked;

        if (!checked) {
            otherWrap.style.display = 'none';
            note.disabled = true;
            note.required = false;
            // reset values
            amount.value = '';
            reason.value = '';
            note.value = '';
        }
    }

    function onReasonChange(id) {
        const reasonVal = document.getElementById('discountReason' + id).value;
        const otherWrap = document.getElementById('discountOtherWrap' + id);
        const note = document.getElementById('discountReasonNote' + id);
        const show = (reasonVal === 'other');

        otherWrap.style.display = show ? '' : 'none';
        note.disabled = !show;
        note.required = show;
    }


    function toggleDiscount2(id) {
        const checked = document.getElementById('hasDiscount2' + id).checked;
        const wrap = document.getElementById('discountWrap2' + id);

        const amount = document.getElementById('discountAmount2' + id);
        const reason = document.getElementById('discountReason2' + id);
        const otherWrap = document.getElementById('discountOtherWrap2' + id);
        const note = document.getElementById('discountReasonNote2' + id);

        wrap.style.display = checked ? '' : 'none';

        amount.disabled = !checked;
        reason.disabled = !checked;
        amount.required = checked;
        reason.required = checked;

        if (!checked) {
            otherWrap.style.display = 'none';
            note.disabled = true;
            note.required = false;
            // reset values
            amount.value = '';
            reason.value = '';
            note.value = '';
        }
    }

    function onReasonChange2(id) {
        const reasonVal = document.getElementById('discountReason2' + id).value;
        const otherWrap = document.getElementById('discountOtherWrap2' + id);
        const note = document.getElementById('discountReasonNote2' + id);
        const show = (reasonVal === 'other');

        otherWrap.style.display = show ? '' : 'none';
        note.disabled = !show;
        note.required = show;
    }

    document.addEventListener('DOMContentLoaded', function() {

        const imageModal = document.getElementById('globalNoteImageModal');
        const imageElement = document.getElementById('globalNoteImage');

        if (!imageModal || !imageElement) {
            return;
        }

        imageModal.addEventListener('show.bs.modal', function(event) {

            const button = event.relatedTarget;

            if (!button) {
                return;
            }

            const imageUrl = button.getAttribute('data-image');

            imageElement.src = imageUrl;

        });

        imageModal.addEventListener('hidden.bs.modal', function() {

            imageElement.src = '';

        });

    });
</script>
