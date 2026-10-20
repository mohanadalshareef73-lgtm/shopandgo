@extends('layout.master')



@section('title')
    <title> اضافة صلاحية جديدة </title>
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
                                <li class="breadcrumb-item"><a href="{{ route('roles.index') }}">الصلاحيات</a></li>
                                <li class="breadcrumb-item" aria-current="page">اضافة صلاحية</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">اضافة صلاحية</h2>
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
                    <form action="{{ route('roles.store') }}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="card">
                            <div class="card-header">
                                <h5>اضافة صلاحية</h5>
                            </div>
                            <div class="card-body">

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">اسم الصلاحية</label>
                                            <input type="text"name="name" class="form-control" id="exampleInputName"
                                                required aria-describedby="nameHelp" placeholder="اسم الصلاحية"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال اسم الصلاحية')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <h4 class="card-title">اذونـات الـصـلاحـيـة</h4>
                                <div class="row">
                                    @php $count = 0; @endphp
                                    @foreach ($permission as $index => $value)
                                        @if ($count % 12 == 0)
                                            @if ($count != 0)
                                </div> <!-- Close the previous row -->
                                @endif
                                <div class="row mt-3"> <!-- Open a new row every 12 items -->
                                    @endif
                                    @if ($count % 4 == 0)
                                        <div class="col-sm-4">
                                            <div class="border p-3 mb-3" style="border-radius: 15px !important;">
                                                <div class="form-check mb-2">
                                                    <input class="form-check-input select-all" type="checkbox"
                                                        id="selectAll{{ floor($count / 4) }}">
                                                    <label class="form-check-label" for="selectAll{{ floor($count / 4) }}">
                                                        تحديد الكل
                                                    </label>
                                                </div>
                                    @endif
                                    <div class="mb-3">
                                        <input class="form-check-input permission-checkbox" type="checkbox"
                                            name="permission[]" value="{{ $value->id }}" id="role{{ $value->id }}">
                                        <label for="role{{ $value->id }}">{{ $value->ar_name }}</label>
                                    </div>
                                    @if (($count + 1) % 4 == 0)
                                </div>
                            </div>
                            @endif
                            @php $count++; @endphp
                            @endforeach


                        </div> <!-- Close the last row -->
                    </div> <!-- Close the last row -->


                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary mb-4">حفظ الصلاحية</button>
                        </div>

                    </form>
                </div>
            </div>



        </div>
    </section>
@endsection


@section('script')
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.select-all').forEach(function(selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    let group = this.closest('.col-sm-4');
                    group.querySelectorAll('.permission-checkbox').forEach(function(checkbox) {
                        checkbox.checked = selectAllCheckbox.checked;
                    });
                });
            });

            document.querySelectorAll('.permission-checkbox').forEach(function(permissionCheckbox) {
                permissionCheckbox.addEventListener('change', function() {
                    let group = this.closest('.col-sm-4');
                    let allChecked = Array.from(group.querySelectorAll('.permission-checkbox'))
                        .every(c => c.checked);
                    group.querySelector('.select-all').checked = allChecked;
                });
            });
        });
    </script>
@endsection
