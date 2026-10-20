@extends('layout.master')



@section('title')
    <title> تعديل بيانات أربـاح </title>
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
                                <li class="breadcrumb-item"><a href="{{ route('profits.index') }}">خدمات
                                        إضافية</a></li>
                                <li class="breadcrumb-item" aria-current="page">تعديل البيانات</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">أربـاح</h2>
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

                            <form action="{{ route('profits.update', $profit->id) }}" method="post"
                                enctype="multipart/form-data">
                                @method('PUT')

                                @csrf
                                <div class="row">

<div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName">قيمة الارباح</label>
                                                    <input type="number" step="any" name="cost"
                                                        value="{{ $profit->cost }}" class="form-control"
                                                        id="exampleInputName" required aria-describedby="nameHelp"
                                                        placeholder="قيمة الارباح"
                                                        oninvalid="this.setCustomValidity('الرجاء ادخال قيمة الارباح')"
                                                        oninput="this.setCustomValidity('')">
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-group">
                                                    <label class="form-label" for="exampleInputName">تاريخ الارباح</label>
                                                    <input type="date" name="date"
                                                        value="{{ $profit->date }}" class="form-control"
                                                        id="exampleInputName" required aria-describedby="nameHelp"
                                                        placeholder="تاريخ الارباح"
                                                        oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ الارباح')"
                                                        oninput="this.setCustomValidity('')">
                                                </div>
                                            </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="platform_select">المنصة </label>
                                                <select name="platform_id" required id="platform_select"
                                                    class="form-control">
                                                    <option value="">-- اختر المنصة --</option>
                                                    @foreach ($platforms as $platform)
                                                        <option value="{{ $platform->id }}" {{ $platform->id == $profit->platform_id ? 'selected' : '' }}>
                                                            {{ $platform->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="currency">العملة </label>
                                                <select name="currency" required id="currency" class="form-control">
                                                    <option value="">-- اختر العملة --</option>
                                                    <option {{ $profit->currency == 'dinar' ? 'selected' : '' }} value="dinar">دينار ليبي - د.ل</option>
                                                    <option {{ $profit->currency == 'dollar' ? 'selected' : '' }} value="dollar">دولار - $</option>
                                                    <option {{ $profit->currency == 'euro' ? 'selected' : '' }} value="euro">يورو - €</option>
                                                </select>
                                            </div>
                                        </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">ملاحظات (إختياري)</label>
                                            <textarea name="notes" class="form-control" placeholder="ملاحظات (إختياري)}">{{ $profit->notes }}</textarea>
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
