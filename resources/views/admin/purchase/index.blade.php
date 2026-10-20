@extends('layout.master')

@section('title')
    <title> المـشـتـريـات </title>
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
                                <h2 class="mb-0">كل المـشـتـريـات </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->



            <div class="row">
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
                                    <h6 class="mb-0">قيمة المشتريات اليومي</h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $totalDay }} <span class="bg-light-primary">د.ل</span>
                                        </h5>
                                    </div>

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> المسددة </span>{{ $totalDayDollar }} <span class="bg-light-primary">$</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> غير مسددة </span> {{ $totalDayEuro }} <span class="bg-light-primary">€</span>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xxl-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">

                                <div class="flex-shrink-0">
                                    <div class="avtar avtar-s bg-light-info">
                                        <span class="pc-micon">
                                            <i class="fas fa-calendar-day" style="font-size: 17px;"></i></span>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0">قيمة المشتريات الشهري</h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $totalMonth }} <span class="bg-light-info">د.ل</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> الاجمالي </span>{{ $totalMonthDollar }} <span class="bg-light-info">$</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> الاجمالي </span> {{ $totalMonthEuro }} <span class="bg-light-info">€</span>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xxl-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0">
                                    <div class="avtar avtar-s bg-light-secondary">
                                        <span class="pc-micon">
                                            <i class="fas fa-calendar-day" style="font-size: 17px;"></i></span>
                                    </div>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h6 class="mb-0">قيمة المشتريات السنوي </h6>
                                </div>
                            </div>
                            <div class="bg-body p-3 mt-3 rounded">
                                <div class="mt-3 row align-items-center">

                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-primary"> الاجمالي </span>{{ $totalYear }} <span class="bg-light-secondary">د.ل</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"> <span class="text-success"> الاجمالي </span>{{ $totalYearDollar }} <span class="bg-light-secondary">$</span>
                                        </h5>
                                    </div>
                                    <div class="col-12">
                                        <h5 class="mb-1"><span class="text-danger"> الاجمالي </span> {{ $totalYearEuro }} <span class="bg-light-secondary">€</span>
                                        </h5>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
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
                            @can('purchase-create')
                                <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                    role="button" aria-expanded="false" aria-controls="collapseExample">اضافة مـشـتـريـات
                                    جديدة</a>
                            @endcan
                            <a class="btn btn-primary m-t-5" style="margin-right: 10px;" data-bs-toggle="collapse"
                                href="#collapseExample2" role="button" aria-expanded="false"
                                aria-controls="collapseExample2">الـبـحـث</a>

                            @can('purchase_description-list')
                                <a class="btn btn-info m-t-5"
                                    style="margin-right: 10px;"href="{{ route('purchaseDesc.index') }}">عـرض الوصـف</a>
                            @endcan

                            @can('purchase-list')
                                <a class="btn btn-secondary m-t-5" style="margin-right: 10px; float: left;" href=".printTable"
                                    data-bs-toggle="modal">طـبـاعـة</a>
                            @endcan

                        </div>


                        <div class="collapse" id="collapseExample2">
                            <div class="card-body">
                                <form action="{{ route('purchase.search') }}" method="get" enctype="multipart/form-data">
                                    @csrf

                                    <div class="row">
                                        @if (isset($query))
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">تحديد الوصف
                                                    </label>
                                                    <select name="desc_id" class="form-control">
                                                        <option>-- اختر الوصـف --</option>
                                                        @foreach ($descriptions as $description)
                                                            @if (isset($query['desc_id']) && $query['desc_id'] == $description->id)
                                                                <option value="{{ $description->id }}">
                                                                    {{ $description->name }}
                                                                @else
                                                                <option value="{{ $description->id }}">
                                                                    {{ $description->name }}
                                                            @endif
                                                            </option>
                                                        @endforeach
                                                    </select>

                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">قيمة
                                                        المشتريات</label>
                                                    <input type="number" step="any" name="cost"
                                                        @if (isset($query['cost'])) value="{{ $query['cost'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="قيمة المشتريات">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">تاريخ
                                                        الشراء</label>
                                                    <input type="date" name="dte"
                                                        @if (isset($query['date'])) value="{{ $query['dte'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="تاريخ الشراء">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">ملاحظات
                                                        (إختياري)</label>
                                                    <input type="date" name="note"
                                                        @if (isset($query['note'])) value="{{ $query['note'] }}" @endif
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="ملاحظات">
                                                </div>
                                            </div>
                                        @else
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">تحديد الوصف
                                                    </label>
                                                    <select name="desc_id" class="form-control">
                                                        <option>-- اختر الوصـف --</option>
                                                        @foreach ($descriptions as $description)
                                                            <option value="{{ $description->id }}">
                                                                {{ $description->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">قيمة
                                                        المشتريات</label>
                                                    <input type="number" step="any" name="cost"
                                                        class="form-control" id="exampleInputName343"
                                                        placeholder="قيمة المشتريات">
                                                </div>
                                            </div>

                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">تاريخ
                                                        الشراء</label>
                                                    <input type="date" name="date" class="form-control"
                                                        id="exampleInputName343" placeholder="تاريخ الشراء">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName343">ملاحظات</label>
                                                    <input type="text" name="note" class="form-control"
                                                        id="exampleInputName343" placeholder="ملاحظات">
                                                </div>
                                            </div>
                                        @endif

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
                                                <a href="{{ route('purchase.index') }}" class="btn btn-primary p-3"><i
                                                        class="fas fa-redo"></i></a>
                                            </div>
                                        </div>

                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('purchase.store') }}" method="post"
                                    enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">

                                        <div class="col-md-4" id="desc_select_container">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">تحديد الوصف</label>
                                                <select name="desc_id" id="desc_select" class="form-control">
                                                    <option>-- اختر الوصـف --</option>
                                                    @foreach ($descriptions as $description)
                                                        <option value="{{ $description->id }}">{{ $description->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-4" style="display: none;" id="new_desc">
                                            <div class="form-group">
                                                <label class="form-label" for="new_desc_name">وصـف جـديـد</label>
                                                <input type="text" name="name" class="form-control"
                                                    id="new_desc_name" placeholder="وصـف جـديـد">
                                            </div>
                                        </div>



                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">قيمة المشتريات</label>
                                                <input type="number" step="any" name="cost" class="form-control"
                                                    id="exampleInputName343" required placeholder="قيمة المشتريات"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال قيمة المشتريات')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>


                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="currency">العملة </label>
                                                <select name="currency" required id="currency" class="form-control">
                                                    <option value="">-- اختر العملة --</option>
                                                    <option selected value="dinar">دينار ليبي - د.ل</option>
                                                    <option value="dollar">دولار - $</option>
                                                    <option value="euro">يورو - €</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="payment_id">طريقة الدفع </label>
                                                <select name="payment_id" required id="payment_id" class="form-control">
                                                    <option value="">-- اختر طريقة الدفع --</option>
                                                    @foreach ($payments as $index => $payment)
                                                    @if ($payment->id == 1)
                                                        <option value="{{ $payment->id }}" selected>{{ $payment->name }}</option>
                                                    @else
                                                        <option value="{{ $payment->id }}">{{ $payment->name }}</option>
                                                    @endif
                                                    @endforeach

                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="newDescription"
                                                        onchange="toggleDescription()">
                                                    <label class="form-check-label" for="newDescription">وصـف
                                                        جـديـد</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName343">تاريخ
                                                    المـشـتـريـات</label>
                                                <input type="date" name="date" class="form-control"
                                                    id="exampleInputName343" required placeholder="تاريخ  المـشـتـريـات"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ  المـشـتـريـات')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">ملاحطات (إختياري)</label>
                                                <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                    placeholder="ملاحظات (إختياري)"></textarea>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <button type="submit" class="btn btn-primary mb-4">حفظ المـشـتـريـات</button>
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
                                                            الــوصــف
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            قيمة المشتريات
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            تـاريـخ المـشـتـريـات
                                                        </th>


                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            ملاحظات
                                                        </th>

                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 285.883px;"
                                                            aria-label="Position: activate to sort column ascending">
                                                            الاجراءات
                                                        </th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @foreach ($purchases as $purchase)
                                                        <tr class="odd">

                                                            <td class="">{{ $purchase->desc->name }}</td>

                                                            <td class="">
                                                                <span style="color: green;">{{ $purchase->cost }}
                                                                    {{ $purchase->currency_sign }}</span>
                                                                    <br>{{ $purchase->payment->name }}
                                                                                                                            </td>

                                                            <td class="">{{ $purchase->date }}</td>

                                                            <td class="">{{ $purchase->note }}</td>

                                                            <td>
                                                                @can('purchase-edit')
                                                                    <a href="{{ route('purchase.edit', $purchase->id) }}"
                                                                        class="btn btn-light-primary">تعديل
                                                                    </a>
                                                                @endcan

                                                                @can('purchase-delete')
                                                                    <button type="button" class="btn btn-light-danger"
                                                                        data-bs-toggle="modal"
                                                                        data-bs-target="#exampleModalLiveDelete{{ $purchase->id }}">حذف</button>

                                                                    <div id="exampleModalLiveDelete{{ $purchase->id }}"
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
                                                                                        هل انت متأكد من حذف المـشـتـريـات <span
                                                                                            class="text-danger">{{ $purchase->desc->name }}</span>
                                                                                        ؟</p>
                                                                                    <form
                                                                                        action="{{ route('purchase.destroy', $purchase->id) }}"
                                                                                        method="post">
                                                                                        @csrf
                                                                                        @method('DELETE')
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
                                                                @endcan

                                                            </td>

                                                        </tr>
                                                    @endforeach

                                                </tbody>

                                                <tfoot>

                                                    <tr>
                                                        <th rowspan="1" colspan="1">الــوصــف</th>
                                                        <th rowspan="1" colspan="1">قيمة المشتريات</th>
                                                        <th rowspan="1" colspan="1">تـاريـخ المـشـتـريـات</th>
                                                        <th rowspan="1" colspan="1">ملاحظات</th>
                                                        <th rowspan="1" colspan="1">الاجراءات</th>
                                                    </tr>

                                                </tfoot>
                                            </table>

                                            @if (isset($query))
                                                <span
                                                    id="paginate">{{ $purchases->appends($query)->links('pagination::bootstrap-4') }}</span>
                                            @else
                                                <span
                                                    id="paginate">{{ $purchases->links('pagination::bootstrap-4') }}</span>
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



    <!-- Modal -->
    <div class="row" dir="rtl">
        <div class="col-12">
            <div class="modal fade printTable" tabindex="-1" role="dialog" aria-labelledby="orderdetailsModalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="orderdetailsModalLabel">طـبـاعـة المـشـتـريـات</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <form action="{{ route('purchase.print') }}" target="_blank" method="GET">
                            @csrf
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="recipient-name"
                                                class="col-form-label">{{ __('messages.startDate') }}</label>
                                            <input type="date" name="startDate" placeholder="من التاريخ"
                                                class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label for="message-text"
                                                class="col-form-label">{{ __('messages.endDate') }}</label>
                                            <input type="date" name="endDate" placeholder="الي التاريخ"
                                                class="form-control">
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="message-text" class="col-form-label">وصـف المـشتريـات</label>
                                            <select name="desc_id" class="form-control">
                                                <option value="">-- اختر الوصـف --</option>
                                                @foreach ($descriptions as $description)
                                                    @if (isset($query['desc_id']) && $query['desc_id'] == $description->id)
                                                        <option value="{{ $description->id }}">
                                                            {{ $description->name }}
                                                        @else
                                                        <option value="{{ $description->id }}">
                                                            {{ $description->name }}
                                                    @endif
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="modal-footer d-flex justify-content-center">
                                    <button type="submit" class="btn btn-primary">طـبـاعـة</button>
                        </form>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إغــلاق</button>
                    </div>
                </div>
                </form>
            </div>
        </div>
    </div>
    <!-- end modal -->


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

        function toggleDescription() {
            const newDescriptionCheckbox = document.getElementById('newDescription');
            const newDescDiv = document.getElementById('new_desc');
            const newDescInpute = document.getElementById('new_desc_name');
            const descSelectContainer = document.getElementById('desc_select_container');
            const descSelect = document.getElementById('desc_select');
            if (newDescriptionCheckbox.checked) {
                newDescDiv.style.display = 'block';
                descSelectContainer.style.display = 'none';
                descSelect.selectedIndex = 0;
            } else {
                newDescDiv.style.display = 'none';
                descSelectContainer.style.display = 'block';
                newDescInpute.value = "";
            }
        }
    </script>
@endsection
