@extends('layout.master')



@section('title')
    <title> تعديل بيانات طريقة الدفع </title>
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
                                <li class="breadcrumb-item"><a href="{{ route('payment-methods.index') }}">خدمات
                                        إضافية</a></li>
                                <li class="breadcrumb-item" aria-current="page">تعديل البيانات</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">طريقة الدفع</h2>
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

                            <form action="{{ route('payment-methods.update', $payment->id) }}" method="post"
                                enctype="multipart/form-data">
                                @method('PUT')

                                @csrf
                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">اسم طريقة الدفع</label>
                                            <input type="text" name="name" value="{{ $payment->name }}"
                                                class="form-control" id="exampleInputName" required
                                                aria-describedby="nameHelp" placeholder="اسم طريقة الدفع"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال اسم طريقة الدفع')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">ملاحظات (إختياري)</label>
                                            <textarea name="notes" class="form-control" placeholder="ملاحظات (إختياري)}">{{ $payment->notes }}</textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-12">

                                        <div class="form-group">
                                            <div class="form-check">
                                                @if ($payment->status)
                                                    <input class="form-check-input" checked type="checkbox" name="status"
                                                        id="invalidCheck">
                                                @else
                                                    <input class="form-check-input" type="checkbox" name="status"
                                                        id="invalidCheck">
                                                @endif

                                                <label class="form-check-label" for="invalidCheck">تفعيل طريقة الدفع</label>
                                            </div>
                                        </div>

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
