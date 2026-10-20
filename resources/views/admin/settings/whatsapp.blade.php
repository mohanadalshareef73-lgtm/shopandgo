@extends('sg.layout')
@php $nav = $nav ?? 'msg'; @endphp
@push('style')
<link rel="stylesheet" href="{{ asset('assets/sg/compat.css') }}?v=1">
@endpush

@section('title')إعدادات واتساب@endsection

@section('content')
<div class="legacy">
<div class="top" style="justify-content:flex-end;margin-bottom:0"><div class="acts"><button class="sg-burger">☰</button></div></div>
    <section class="pc-container">
        <div class="pc-content">
            <div class="page-header">
                <div class="page-block">
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('admin.home') }}">الرئيسية</a></li>
                        <li class="breadcrumb-item" aria-current="page">إعدادات واتساب</li>
                    </ul>
                    <div class="page-header-title"><h2 class="mb-0">إعدادات واتساب</h2></div>
                </div>
            </div>

            @include('layout.validation-messages')

            <div class="row">
                <div class="col-lg-10 col-xl-8">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between gap-3 flex-wrap">
                            <h5 class="mb-0">أرقام حساب Whatsy</h5>
                            <a href="{{ route('settings.whatsapp.edit') }}" class="btn btn-outline-primary btn-sm">تحديث الأرقام</a>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">تظهر الأرقام المرتبطة بحسابك تلقائياً. اختر الرقم الذي يرسل إشعارات الطلبات والشحن.</p>

                            @if ($loadError)
                                <div class="alert alert-warning" role="alert">
                                    <p class="mb-1">{{ $loadError }}</p>
                                    <p class="mb-0">الرقم الرئيسي المحفوظ لم يتغير.
                                        @if ($mainSessionUuid)
                                            <strong dir="ltr">{{ $savedMain['name'] ?? $mainSessionUuid }}</strong>
                                        @endif
                                    </p>
                                </div>
                            @elseif (empty($sessions))
                                <div class="alert alert-light border" role="status">
                                    لا توجد أرقام مرتبطة بحساب Whatsy. اربط رقماً في Whatsy ثم اضغط «تحديث الأرقام».
                                </div>
                                @if ($mainSessionUuid)
                                    <p class="text-muted">الرقم الرئيسي المحفوظ غير موجود في القائمة الحالية. لم يتم تغييره تلقائياً.</p>
                                @endif
                            @else
                                @if ($mainSessionUuid && ! collect($sessions)->contains('uuid', $mainSessionUuid))
                                    <div class="alert alert-warning" role="alert">الرقم الرئيسي المحفوظ لم يعد موجوداً في الحساب. اختر رقماً آخر للرسائل التلقائية.</div>
                                @endif
                                <form action="{{ route('settings.whatsapp.update') }}" method="post">
                                    @csrf
                                    @method('PUT')
                                    <div class="table-responsive">
                                        <table class="table align-middle">
                                            <caption class="visually-hidden">أرقام واتساب المتاحة واختيار الرقم الرئيسي للرسائل التلقائية</caption>
                                            <thead><tr><th scope="col">الرقم / اسم الجلسة</th><th scope="col">حالة الاتصال</th><th scope="col">الرقم الرئيسي</th></tr></thead>
                                            <tbody>
                                                @foreach ($sessions as $session)
                                                    <tr>
                                                        <td>
                                                            <label class="mb-0 fw-semibold" dir="ltr" for="whatsapp-main-{{ $session['uuid'] }}">{{ $session['name'] }}</label>
                                                            @if ($session['uuid'] === $mainSessionUuid)<span class="badge bg-light-primary ms-2">الرئيسي الحالي</span>@endif
                                                        </td>
                                                        <td>
                                                            @if ($session['status'] === 'connected')
                                                                <span class="badge bg-light-success">متصل</span>
                                                            @elseif ($session['status'] === 'disconnected')
                                                                <span class="badge bg-light-danger">غير متصل</span>
                                                            @else
                                                                <span class="badge bg-light-secondary">غير معروف</span>
                                                            @endif
                                                        </td>
                                                        <td>
                                                            <input type="radio" class="form-check-input" name="main_session_uuid"
                                                                id="whatsapp-main-{{ $session['uuid'] }}" value="{{ $session['uuid'] }}" required
                                                                aria-label="استخدام {{ $session['name'] }} للرسائل التلقائية"
                                                                @checked(old('main_session_uuid', $mainSessionUuid) === $session['uuid'])
                                                                @cannot('system-edit') disabled @endcannot>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @error('main_session_uuid')<div class="text-danger mb-3" role="alert">{{ $message }}</div>@enderror
                                    <p class="text-muted small">حالة الاتصال هي آخر حالة معروفة لدى Whatsy. إذا انقطع اتصال الرقم الرئيسي، يفشل الإرسال ويظهر في سجل الأخطاء.</p>
                                    @can('system-edit')<button type="submit" class="btn btn-primary">حفظ الرقم الرئيسي</button>@endcan
                                </form>
                            @endif
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <h5>الردود ورسائل عروض الأسعار</h5>
                            <p class="mb-2">ترسل الردود وتأكيد استلام السلة وملفات عروض الأسعار من الرقم الذي استقبل رسالة العميل، مهما كان الرقم الرئيسي.</p>
                            <p class="text-muted mb-0">اضبط رابط webhook لكل رقم في Whatsy ليتمكن من استقبال رسائل العملاء.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>
@endsection
