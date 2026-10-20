@extends('layout.master')

@section('title')
    <title> الخزينة </title>
@endsection

@section('style')
    <!-- data tables css -->
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/dataTables.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/select.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/autoFill.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/keyTable.bootstrap5.min.css') }}">
    <!-- [Page specific CSS] end -->
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
                                <li class="breadcrumb-item" aria-current="page">القائمة</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">الخزينة
                                    @if($currency == "dinar")
                                    <span class="text-primary">دينار ليبي</span>
                                    @elseif ($currency == "dollar")
                                    <span class="text-primary">دولار $</span>
                                    @else
                                    <span class="text-primary">يورو €</span>
                                    @endif
                                </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->


<div class="row">

    @foreach ($treasury as $tt)

                        <div class="col-md-6 col-xxl-4">
                            <div class="card">
                                <div class="card-body">
                                    <div class="d-flex align-items-center">
                                        <div class="flex-shrink-0">
                                            <div class="avtar avtar-s bg-light-primary">
                                                <span class="pc-micon">
                                                    <i class="fas fa-calendar-day" style="font-size: 17px;"></i></span>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 ms-3">
                                            <h6 class="mb-0">{{ $tt->payment->name }}</h6>
                                        </div>
                                    </div>
                                    <div class="bg-body p-3 mt-3 rounded">
                                        <div class="mt-3 row align-items-center">

                                            <div class="col-12">
                                                <h5 class="mb-1"> <span class="text-success">
                                                    </span>{{ $tt->total }} <span
                                                        class="bg-light-primary">{{ $currency }}</span>
                                                </h5>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
    @endforeach


                    </div>


            @include('layout.validation-messages')

            <!-- [ Main Content ] start -->
            <div class="row">
                <!-- Autofill table start -->
                <div class="col-sm-12">
                    <div class="card">
                        {{-- <div class="card-header">
                        <a href="{{ route('ad.create') }}" class=" btn btn-outline-success">{{ __('messages.ad.add') }}</a>
                    </div> --}}
                        <div class="card-header">

                            @can('treasury-edit')
                                <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                    role="button" aria-expanded="false" aria-controls="collapseExample">تعديل قيمة الخزينة</a>
                            @endcan
                            <a class="btn btn-primary m-t-5" style="margin-right: 10px;" data-bs-toggle="collapse"
                                href="#collapseExample2" role="button" aria-expanded="false"
                                aria-controls="collapseExample2">الـبـحـث</a>
                        </div>


                        <div class="collapse" id="collapseExample2">
                            <div class="card-body">
                                <form action="{{ route('safe.search') }}" method="get" enctype="multipart/form-data">
                                    @csrf

                                    <div class="row">
                                        @if (isset($query))
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">من التاريخ </label>
                                                    <input type="date" name="from"
                                                        @if (isset($query['from'])) value="{{ $query['from'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="من التاريخ  ">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">الي التاريخ </label>
                                                    <input type="date" name="to"
                                                        @if (isset($query['to'])) value="{{ $query['to'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="الي التاريخ">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">حالة الحركة</label>

                                                    <select name="status" id="trip_select" class="form-control">
                                                        <option>-- اختر حالة الحركة --</option>

                                                        @if (isset($query['status']) && $query['status'] == 'pay')
                                                            <option selected value="pay">اضافة قيمة</option>
                                                            <option value="pull">>سحب القيمة</option>
                                                            <option value="update">تعديل الخزينة</option>
                                                        @elseif (isset($query['status']) && $query['status'] == 'pull')
                                                            <option value="pay">اضافة قيمة</option>
                                                            <option selected value="pull">>سحب القيمة</option>
                                                            <option value="update">تعديل الخزينة</option>
                                                        @elseif (isset($query['status']) && $query['status'] == 'update')
                                                            <option value="pay">اضافة قيمة</option>
                                                            <option value="pull">>سحب القيمة</option>
                                                            <option selected value="update">تعديل الخزينة</option>
                                                        @endif
                                                    </select>
                                                </div>
                                            </div>

                                        @else
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">من التاريخ </label>
                                                    <input type="date" name="from" class="form-control"
                                                        id="exampleInputName343" placeholder="من التاريخ  ">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">الي التاريخ </label>
                                                    <input type="date" name="to" class="form-control"
                                                        id="exampleInputName343" placeholder="الي التاريخ">
                                                </div>
                                            </div>
                                        @endif

                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">حالة الحركة</label>

                                                <select name="status" id="trip_select" class="form-control">
                                                    <option>-- اختر حالة الحركة --</option>
                                                        <option value="pay">اضافة قيمة</option>
                                                        <option value="pull">>سحب القيمة</option>
                                                        <option value="update">تعديل الخزينة</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <div class="form-group pt-4">
                                                <label class="form-label" for="exampleInputName343"></label>
                                                <button type="submit" class="btn btn-primary p-3"><i
                                                        class="fas fa-search"></i></button>
                                            </div>
                                        </div>

                                        <div class="col-md-2">
                                            <div class="form-group pt-4">
                                                <label class="form-label" for="exampleInputName343"></label>
                                                <a href="{{ route('safe.index') }}" class="btn btn-primary p-3"><i
                                                        class="fas fa-redo"></i></a>
                                            </div>
                                        </div>

                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('safe.update', [$currency]) }}" method="post"
                                    enctype="multipart/form-data">
                                    @method('PUT')
                                    @csrf
                                    <div class="row">

                                        <input name="currency" type="hidden" value="{{ $currency }}" required>

    @foreach ($treasury as $tt)
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">قيمة الخزينة {{ $currencySign }} - {{ $tt->payment->name }}</label>
                                                    <input type="number" step="any" value="{{ auth()->user()->safe() }}"
                                                        name="total{{ $tt->payment_id }}" class="form-control" id="exampleInputName343" required
                                                        placeholder="قيمة الخزينة {{ $currencySign }}"
                                                        oninvalid="this.setCustomValidity('الرجاء ادخال قيمة الخزينة')"
                                                        oninput="this.setCustomValidity('')">
                                                </div>
                                            </div>
                                        @endforeach


                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ التعديلات</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="dt-responsive table-responsive">
                                <div id="autofill_wrapper" class="dataTables_wrapper dt-bootstrap5">
                                    <div class="row dt-row">
                                        <div class="col-sm-12">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Name: activate to sort column ascending">
                                                            القيمة
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            قبل وبعد
                                                        </th>


                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            حالة الحركة
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            سجلت من
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            التاريخ والوقت
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            المنشئ
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 285.883px;"
                                                            aria-label="Position: activate to sort column ascending">
                                                            الاجراءات
                                                        </th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @foreach ($history as $histo)
                                                        <tr class="odd">

                                                            <td class="" style="color: green;">
                                                                {{ $histo->price }} {{ $histo->currency_sign }}
                                                                <br>{{ $histo->payment->name }}
                                                            </td>

                                                            <td class="">
                                                                قيمة الخزينة قبل <span class="text-success">{{ $histo->price_before }} DLY</span>
                                                                <br> قيمة الخزينة بعد <span class="text-success">{{ $histo->price_after }} DLY</span>
                                                            </td>

                                                            <td class="text-info">
                                                                @if ($histo->status == 'pay')
                                                                    <span class="text-success">اضافة قيمة</span>
                                                                @elseif($histo->status == 'pull')
                                                                    <span class="text-danger">سحب القيمة</span>
                                                                @elseif($histo->status == 'update')
                                                                    <span class="text-warning">تعديل الخزينة</span>
                                                                @endif
                                                            </td>

                                                            <td class="text-info">
                                                                @if ($histo->type == 'order')
                                                                    @if ($histo->order)
                                                                        <span class="text-primary">الطلبية
                                                                            {{ $histo->order->code }} -
                                                                            {{ $histo->order->customer->name }} </span>
                                                                    @else
                                                                        <span class="text-primary">طلبية غير موجودة</span>
                                                                    @endif
                                                                    @if ($histo->is_update)
                                                                        <br><span class="text-primary">تعديل الطلبية</span>
                                                                    @endif
                                                                @elseif($histo->type == 'trip')
                                                                    @if ($histo->trip)
                                                                        <span class="text-primary">الرحلة
                                                                            {{ $histo->trip->code }} -
                                                                            {{ $histo->trip->country }} </span>
                                                                    @else
                                                                        <span class="text-primary">الرحلة غير موجودة</span>
                                                                    @endif
                                                                    @if ($histo->is_update)
                                                                        <br><span class="text-primary">تعديل الرحلة</span>
                                                                    @endif

                                                                @elseif($histo->type == 'customer')
                                                                    @if ($histo->customer)
                                                                        <span class="text-primary">الزبون
                                                                            {{ $histo->customer->code }} -
                                                                            {{ $histo->customer->name }} </span>
                                                                    @else
                                                                        <span class="text-primary">الزبون غير موجودة</span>
                                                                    @endif
                                                                    @if ($histo->is_update)
                                                                        <br><span class="text-primary">تعديل الزبون</span>
                                                                    @endif

                                                                @elseif($histo->type == 'shippingCharge')
                                                                    @if ($histo->shippingCharge)
                                                                        <span class="text-primary">مصروفات الشحن
                                                                            {{ $histo->shippingCharge->type->name }} -
                                                                            {{ $histo->shippingCharge->weight }} </span>
                                                                    @else
                                                                        <span class="text-primary">مصروفات الشحن غير موجودة</span>
                                                                    @endif
                                                                    @if ($histo->is_update)
                                                                        <br><span class="text-primary">تعديل مصروفات الشحن</span>
                                                                    @endif

                                                                @elseif($histo->type == 'purchase')
                                                                    @if ($histo->purchase)
                                                                        <span class="text-primary">المشتريات
                                                                            {{ $histo->purchase->desc->name }} -
                                                                            {{ $histo->purchase->date }} </span>
                                                                    @else
                                                                        <span class="text-primary">المشتريات غير موجودة</span>
                                                                    @endif
                                                                    @if ($histo->is_update)
                                                                        <br><span class="text-primary">تعديل المشتريات</span>
                                                                    @endif

                                                                @elseif($histo->type == 'paymentExpenses')
                                                                    @if ($histo->paymentExpenses)
                                                                        <span class="text-primary">مصروفات الدفع
                                                                            {{ $histo->paymentExpenses->from }} -
                                                                            {{ $histo->paymentExpenses->to }}
                                                                        - {{ $histo->paymentExpenses->platform->name }} </span>
                                                                    @else
                                                                        <span class="text-primary">مصروفات الدفع غير موجودة</span>
                                                                    @endif
                                                                    @if ($histo->is_update)
                                                                        <br><span class="text-primary">تعديل مصروفات الدفع</span>
                                                                    @endif

                                                                @elseif($histo->type == 'user')
                                                                    @if ($histo->user)
                                                                        <span class="text-primary">المستخدم
                                                                            {{ $histo->user->name }}</span>
                                                                    @else
                                                                        <span class="text-primary">المستخدم غير موجودة</span>
                                                                    @endif
                                                                    @if ($histo->is_update)
                                                                        <br><span class="text-primary">تعديل المستخدم</span>
                                                                    @endif

                                                                @elseif($histo->type == 'dept')
                                                                    @if ($histo->dept)
                                                                        <span class="text-primary">دين العميل
                                                                            @if ($histo->dept->customer)
                                                                            {{ $histo->dept->customer->name }}</span>
                                                                            @else
                                                                            {{ $histo->dept->org }}</span>
                                                                            @endif
                                                                    @else
                                                                        <span class="text-primary">الدين غير موجود</span>
                                                                    @endif
                                                                    @if ($histo->is_update)
                                                                        <br><span class="text-primary">تعديل الدين</span>
                                                                    @endif

                                                                @elseif(in_array($histo->type, ['trip_purchase', 'trip_purchase_reverse']))
                                                                    <span class="text-primary">رحلة شراء</span>
                                                                @elseif(in_array($histo->type, ['exchange', 'exchange_reverse']))
                                                                    <span class="text-primary">شراء دولار / تحويل عملة</span>
                                                                @elseif(in_array($histo->type, ['partner', 'partner_reverse']))
                                                                    <span class="text-primary">حركة شريك</span>
                                                                @endif
                                                                @if ($histo->note)
                                                                    <br><small class="text-muted">{{ $histo->note }}</small>
                                                                @endif
                                                            </td>

                                                            <td class="" style="color: green;">
                                                                {{ $histo->created_at->format('Y-m-d') }} - {{ $histo->created_at->format('H:m:s') }} </td>

                                                            <td class="" style="color: green;">
                                                                {{ $histo->createdUser->name }} </td>

                                                            <td>

                                                                @can('treasury-delete')
                                                                @if ($histo->status != "update")
                                                                    <button type="button" class="btn btn-light-danger"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#exampleModalLiveDelete{{ $histo->id }}">حذف</button>

                                                                    <div id="exampleModalLiveDelete{{ $histo->id }}"
                                                                        class="modal fade" tabindex="-1" role="dialog"
                                                                        aria-labelledby="exampleModalLiveLabel"
                                                                        aria-hidden="true">
                                                                        <div class="modal-dialog modal-dialog-centered"
                                                                            role="document">
                                                                            <div class="modal-content">
                                                                                <div class="modal-body text-center">
                                                                                    <div>
                                                                                        <i
                                                                                            class="ti ti-alert-circle text-danger fa-5x mb-3"></i>
                                                                                    </div>
                                                                                    <p style="font-size: 18px;">
                                                                                        هل انت متأكد من حذف هذا السجل<br> بقيمة  <span
                                                                                            class="text-danger">{{ $histo->price }}</span>
                                                                                            التي تم
                                                                                            @if ($histo->status == 'pay')
                                                                                            <span class="text-success">اضافته</span>
                                                                                        @elseif($histo->status == 'pull')
                                                                                            <span class="text-danger">سحبه</span>
                                                                                        @elseif($histo->status == 'update')
                                                                                            <span class="text-warning">تعديله</span>
                                                                                        @endif
                                                                                        ؟</p>
                                                                                    <form
                                                                                        action="{{ route('safe.destroy', $histo->id) }}"
                                                                                        method="post">
                                                                                        @csrf
                                                                                        @method('DELETE')

                                                                                        <input name="currency" type="hidden" value="{{ $currency }}" required>
                                                                                </div>
                                                                                <div
                                                                                    class="modal-footer d-flex justify-content-center">
                                                                                    <button type="submit"
                                                                                        class="btn btn-danger">نـعـم</button>
                                                                                    </form>
                                                                                    <button type="button"
                                                                                        class="btn btn-secondary"
                                                                                        data-bs-dismiss="modal">إغـلاق</button>
                                                                                </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    @endif
                                                                @endcan

                                                            </td>

                                                        </tr>
                                                    @endforeach

                                                </tbody>

                                                <tfoot>

                                                    <tr>
                                                        <th rowspan="1" colspan="1">القيمة</th>
                                                        <th rowspan="1" colspan="1">قبل وبعد</th>
                                                        <th rowspan="1" colspan="1">حالة الحركة</th>
                                                        <th rowspan="1" colspan="1">سجلت من</th>
                                                        <th rowspan="1" colspan="1">التاريخ والوقت</th>
                                                        <th rowspan="1" colspan="1">المنشئ</th>

                                                        <th rowspan="1" colspan="1">الاجراءات</th>
                                                    </tr>

                                                </tfoot>
                                            </table>

                                            @if (isset($query))
                                                <span
                                                    id="paginate">{{ $history->appends($query)->links('pagination::bootstrap-4') }}</span>
                                            @else
                                                <span
                                                    id="paginate">{{ $history->links('pagination::bootstrap-4') }}</span>
                                            @endif
                                        </div>
                                    </div>

                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- Autofill table end -->

            </div>
            <!-- [ Main Content ] end -->
        </div>
    </section>


    <script>
        function redirectToAd(route) {
            // Redirect to the specified route
            window.location.href = route;
        }
    </script>
@endsection
@section('script')
    <!-- datatable Js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    {{-- <script src="../assets/js/plugins/apexcharts.min.js"></script>
 <script src="../assets/js/pages/dashboard-default.js"></script> --}}

    <script src="{{ asset('assets/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.autoFill.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/autoFill.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.keyTable.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/keyTable.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.select.min.js') }}"></script>

    <script>
        // [ Autofill ]
        $('#autofill').DataTable({
            autoFill: true
        });
        // [ KeyTable Integration ]
        $('#key-integration').DataTable({
            keys: true,
            autoFill: true
        });
        // [ Column Selector ]
        $('#confirm-table').DataTable({
            autoFill: {
                alwaysAsk: true
            }
        });

        // [ scroll fill ]
        var safill = $('#scroll-fill').dataTable({
            scrollY: 400,
            scrollCollapse: true,
            paging: false,
            autoFill: true
        });
        $('#colum-select').DataTable({
            columnDefs: [{
                orderable: false,
                className: 'select-checkbox',
                targets: 0
            }],
            select: {
                style: 'os',
                selector: 'td:first-child'
            },
            order: [
                [1, 'asc']
            ],
            autoFill: {
                columns: ':not(:first-child)'
            }
        });


    </script>
@endsection
