@extends('layout.master')

@section('title')
    <title> تعديل بيانات رسالة الواتساب </title>
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
                                <h2 class="mb-0">تعديل بيانات رسالة الواتساب <span
                                        class="text-primary">{{ $message->title }}</span> </h2>
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

                            <form action="{{ route('whatsapp-message.update', $message->id) }}" method="post"
                                enctype="multipart/form-data">
                                @method('PUT')

                                @csrf
                                <div class="row">

                                    <div class="col-md-12">
                                        <p class="text-warning" style="text-align: center; font-size: 18px;;">
                                            ملاحظة :: هناك متغيرات تابثة نرجو منك عدم تغيرها مكتوبة باللغة الانجليزية وعدم اضافة ايموجي
                                            <br>
                                                                                        n\ تعني سطر جديد

                                        </p>
                                    </div>

                                </div>

                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">عنوان رسالة الواتساب</label>
                                            <input type="text" name="name" disabled value="{{ $message->title }}"
                                                class="form-control" id="exampleInputName343" required
                                                placeholder="عنوان رسالة الواتساب"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال عنوان رسالة الواتساب')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>


                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">محتوي رسالة الواتساب</label>
                                            <textarea type="text" name="message" class="form-control" id="exampleInputName" aria-describedby="nameHelp" rows="15"
                                                placeholder="محتوي رسالة الواتساب" required oninvalid="this.setCustomValidity('الرجاء ادخال محتوي رسالة الواتساب')"
                                                oninput="this.setCustomValidity('')">{{ $message->message }}</textarea>
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
