@extends('layout.master')

@section('title')
    <title> تعديل بيت الزبائن </title>
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
                                <h2 class="mb-0">تعديل بيانات <span class="text-primary">{{ $customer->name }}</span> </h2>
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

                            <form action="{{ route('customer.update', $customer->id) }}" method="post" enctype="multipart/form-data">
                                @method('PUT')

                                @csrf
                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">اسم الزبون</label>
                                            <input type="text" name="name" value="{{ $customer->name }}" class="form-control" id="exampleInputName343"
                                                required placeholder="اسم الزبون"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال اسم الزبون')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">رقم الهاتف</label>
                                            <input type="text" name="phone_1" value="{{ $customer->phone_1 }}" class="form-control" id="exampleInputName343"
                                                required placeholder="رقم الهاتف"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال رقم الهاتف')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">رقم الهاتف 2 (إختياري)</label>
                                            <input type="text" name="phone_2" value="{{ $customer->phone_2 }}" class="form-control" id="exampleInputName343"
                                                placeholder="رقم الهاتف 2 (إختياري)">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">كود الزبون
                                              </label>
                                            <input type="text" value="{{ $customer->code }}" name="code" class="form-control"
                                                id="exampleInputName343" placeholder="كود الزبون" required
                                                oninvalid="this.setCustomValidity('الرجاء ادخال كود الزبون')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">العنوان (إختياري)</label>
                                            <input type="text" name="address" value="{{ $customer->address }}" class="form-control" id="exampleInputName343"
                                                placeholder="العنوان (إختياري)">
                                        </div>
                                    </div>



<div class="col-md-12 pb-4 pt-4">
    <div class="form-check">
        <input type="checkbox" name="reset_password" class="form-check-input" id="resetPasswordCheckbox">
        <label class="form-check-label" for="resetPasswordCheckbox">
            إعادة تعيين كلمة المرور الخاصة بالزبون
            <span class="text-warning d-block">
                ملاحظة: كلمة المرور ستصبح رقم الهاتف الخاص به
            </span>
        </label>
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
