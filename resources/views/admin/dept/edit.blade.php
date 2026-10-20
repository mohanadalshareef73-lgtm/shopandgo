@extends('layout.master')

@section('title')
    <title> تعديل بيت الزبائن </title>
@endsection

@section('style')
    <!-- data tables css -->
    <link rel="stylesheet" href="{{ asset('assets\select2\css\select2.min.css') }}">

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
                                <li class="breadcrumb-item" aria-current="page">تعديل البيانات</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                @if ($dept->org)
                                    <h2 class="mb-0">تعديل بيانات <span class="text-primary">{{ $dept->org }}</span>
                                    </h2>
                                @else
                                    <h2 class="mb-0">تعديل بيانات <span
                                            class="text-primary">{{ $dept->customer->name }}</span> </h2>
                                @endif
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

                            <form action="{{ route('dept.update', $dept->id) }}" method="post"
                                enctype="multipart/form-data">
                                @method('PUT')

                                @csrf
                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="customer_select">الزبون (إختياري)</label>
                                            <br>
                                            <select name="customer_id" style="width: 100%;"
                                                class="form-control js-example-basic-single" multiple="single"
                                                id="customer_select" data-placeholder="اختر الزبون">
                                                @foreach ($customers as $customer)
                                                    @if ($dept->customer_id == $customer->id)
                                                        <option selected value="{{ $customer->id }}">{{ $customer->name }} -
                                                            {{ $customer->code }}</option>
                                                    @else
                                                        <option value="{{ $customer->id }}">{{ $customer->name }} -
                                                            {{ $customer->code }}</option>
                                                    @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">اسم الجهة (إختياري)</label>
                                            <input type="text" name="org_name" class="form-control"
                                                @if ($dept->org) value="{{ $dept->org }}" @endif
                                                id="exampleInputName343" placeholder="اسم الجهة (إختياري)">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">قيمة الدين</label>
                                            <input type="number" value="{{ $dept->total }}" step="any" name="total"
                                                class="form-control" id="exampleInputName343" required
                                                placeholder="قيمة الدين"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال قيمة الدين')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تاريخ
                                                مصروفات الدفع</label>
                                            <input type="date" name="date" value="{{ $dept->date }}"
                                                class="form-control" id="exampleInputName343" value="{{ $dept->total }}"
                                                required placeholder="تاريخ  مصروفات الدفع"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ  مصروفات الدفع')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">ملاحطات (إختياري)</label>
                                            <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                placeholder="ملاحظات (إختياري)">{{ $dept->note }}</textarea>
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
    <script src="{{ asset('assets\select2\js\select2.full.min.js') }}" charset="utf-8"></script>

    <script>
        $(document).ready(function() {
            $('.js-example-basic-single').select2({
                dir: "rtl",
                maximumSelectionLength: 1,
            });
            $('.js-example-basic-multiple').select2();

            document.getElementById("rtl-mode-switch").trigger('click');
        });
    </script>
@endsection
