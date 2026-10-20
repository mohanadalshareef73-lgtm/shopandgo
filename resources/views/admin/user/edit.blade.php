@extends('layout.master')



@section('title')
    <title> تعديل بيانات المستخدم </title>
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
                                <li class="breadcrumb-item"><a href="{{ route('users.index') }}">المستخدمين</a></li>
                                <li class="breadcrumb-item" aria-current="page">تعديل البيانات</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">المستخدم</h2>
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

                            <form action="{{ route('users.update', $user->id) }}" method="post" enctype="multipart/form-data">
                                @method('PUT')

                                @csrf
                                <div class="row">

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">اسم المستخدم</label>
                                            <input type="text"name="name"  value="{{ $user->name }}" class="form-control" id="exampleInputName"
                                                required placeholder="اسم المستخدم"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال اسم المستخدم')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">البريد الالكتروني</label>
                                            <input type="email" disabled name="email" class="form-control"
                                                id="exampleInputName" value="{{ $user->email }}" required aria-describedby="nameHelp"
                                                placeholder="البريد الالكتروني"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال البريد الالكتروني')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="password">كلمة مرور جديدة</label>
                                            <input type="password" name="password" class="form-control" id="password"
                                             aria-describedby="nameHelp" placeholder="كلمة المرور">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="password-confirm">إعادة كلمة المرور الجديدة</label>
                                            <input type="password" name="password_confirmation" class="form-control"
                                                id="password-confirm" aria-describedby="nameHelp"
                                                placeholder="كلمة المرور">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleFormControlSelect1">الـصـلاحـيـة</label>
                                            <select class="form-select" name="roles" required
                                                oninvalid="this.setCustomValidity('الرجاء تحديد صلاحية المستخدم')"
                                                oninput="this.setCustomValidity('')" id="exampleFormControlSelect1">
                                                <option value="{{$user->roles->first()->id}}" selected>{{$user->roles->first()->name}}</option>
                                                @foreach ($roles as $role)
                                                <option value="{{$role->id}}">{{$role->name}}</option>
                                            @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <div class="form-check">
                                                @if ($user->status)
                                                <input class="form-check-input" checked type="checkbox" name="status"
                                                id="invalidCheck">
                                                @else
                                                <input class="form-check-input" type="checkbox" name="status"
                                                    id="invalidCheck">
                                                @endif

                                                <label class="form-check-label" for="invalidCheck">تفعيل الحساب</label>
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
