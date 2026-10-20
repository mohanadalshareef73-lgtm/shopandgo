@extends('layout.master')

@section('title')
    <title> تعديل بيانات نوع الشحن </title>
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
                                <li class="breadcrumb-item"><a href="{{ route('shippingType.index') }}">انواع الشحن</a></li>
                                <li class="breadcrumb-item" aria-current="page">تعديل البيانات</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">تعديل بيانات نوع الشحن <span class="text-primary">{{ $type->name }}</span> </h2>
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

                            <form action="{{ route('shippingType.update', $type->id) }}" method="post" enctype="multipart/form-data">
                                @method('PUT')

                                @csrf
                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">اسم نوع الشحن</label>
                                            <input type="text" name="name" value="{{ $type->name }}" class="form-control" id="exampleInputName343"
                                                required placeholder="اسم نوع الشحن"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال اسم نوع الشحن')"
                                                oninput="this.setCustomValidity('')">
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
