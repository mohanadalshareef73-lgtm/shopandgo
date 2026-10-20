@extends('layout.master')

@section('title')
    <title> تعديل الاشعار </title>

    <link rel="stylesheet" href="{{ asset('assets\select2\css\select2.min.css') }}">

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
                            <li class="breadcrumb-item"><a href="{{ route('notification.index') }}">الاشعارات</a></li>
                            <li class="breadcrumb-item" aria-current="page">القائمة</li>
                        </ul>
                    </div>
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">تعديل الاشعار</h2>
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
                        <h5>بطاقة الاشعار</h5>
                    </div>
                    <div class="card-body">

                        <form action="{{ route('notification.update',$notification->id)}}" method="post" enctype="multipart/form-data">
                            @method('PUT')
                        @csrf
                            <div class="row">

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="exampleInputEmail1">عنوان الاشعار</label>
                                        <input type="text" name="title" value="{{ $notification->title }}" class="form-control"
                                            id="exampleInputEmail1" required aria-describedby="emailHelp"
                                            oninvalid="this.setCustomValidity('الرجاء ادخال عنوان الاشعار')"
                                            oninput="this.setCustomValidity('')" placeholder="عنوان الاشعار">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="exampleInputEmail1">نبذة عن الاشعار</label>
                                        <input type="text" name="disc" value="{{ $notification->disc }}" class="form-control"
                                            id="exampleInputEmail1" required aria-describedby="emailHelp"
                                            oninvalid="this.setCustomValidity('الرجاء ادخال نبذة عن الاشعار')"
                                            oninput="this.setCustomValidity('')" placeholder="نبذة عن الاشعار">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="exampleFormControlSelect1">تحديد نوع
                                            الجهة</label>
                                        <select class="form-select" name="type" id="exampleFormControlSelect1">
                                            @if ($notification->typeOf == "some")
                                                <option value="all">الكـل</option>
                                                <option selected value="some">بعض المستخدمين</option>
                                            @elseif($notification->typeOf == "all")
                                                <option selected value="all">الكـل</option>
                                                <option value="some">بعض المستخدمين</option>
                                            @else
                                                <option value="all">الكـل</option>
                                                <option value="some">بعض المستخدمين</option>
                                            @endif
                                        </select>
                                    </div>
                                </div>


                                @if ($notification->typeOf == "some")
                                    <div class="col-md-6" id="selectSomeOrgs">
                                @else
                                    <div class="col-md-6" style="display: none;" id="selectSomeOrgs">
                                @endif
                                    <div class="form-group">
                                        <label class="form-label" for="exampleInputEmail133">تحديد المستخدمين</label>
                                        <br>
                                        <select name="orgs[]" style="width: 100%; height: 300px;" class="form-control js-example-basic-multiple"
                                            multiple="multiple" id="exampleInputEmail133">
                                            @if($notification->typeOf == "some")
                                                @foreach ($notification->orgs as $org2)
                                                    <option selected value="{{ $org2->org->id }}">{{ $org2->org->name }}</option>
                                                @endforeach
                                            @endif

                                            @foreach ($orgs as $org)
                                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>


                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="exampleInputName">صورة من الاشعار
                                            (إختياري)</label>
                                        <input type="file" name="img" class="form-control"
                                            id="exampleInputName" aria-describedby="nameHelp"
                                            accept=".pdf,image/jpeg,image/png,image/jpg"
                                            placeholder="صورة من الاشعار">
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label" for="exampleInputName333">تاريخ الاشعار
                                            (إختياري)</label>
                                        <input type="date" name="date" value="{{ $notification->date }}" class="form-control"
                                            id="exampleInputName333" aria-describedby="nameHelp"
                                            placeholder="تاريخ الاشعار">
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

            document.addEventListener("DOMContentLoaded", function() {
        const selectType = document.getElementById('exampleFormControlSelect1');
        const selectSomeOrgs = document.getElementById('selectSomeOrgs');

        // Listen for changes on the first select
        selectType.addEventListener('change', function() {
            if (selectType.value === 'some') {
                selectSomeOrgs.style.display = 'block';
            } else {
                selectSomeOrgs.style.display = 'none';
            }
        });

        // Check initial value
        if (selectType.value === 'some') {
            selectSomeOrgs.style.display = 'block';
        } else {
            selectSomeOrgs.style.display = 'none';
        }
    });
</script>
@endsection
