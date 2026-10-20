```blade
<!DOCTYPE html>
<html lang="en">

<head>

    <title>لوحة التحكم - الزبائن</title>

    <meta charset="utf-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="description" content="SHOP AND GO">
    <meta name="keywords" content="SHOP AND GO">
    <meta name="author" content="Malik Al Nabouli">

    <link rel="icon"
        href="{{ asset('assets/images/customersLogo.png') }}"
        type="image/x-icon">


    {{-- =========================
         CSS
    ========================== --}}

    <link href="{{ asset('assets/css/plugins/animate.min.css') }}"
        rel="stylesheet"
        type="text/css" />

    <link href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css"
        rel="stylesheet" />

    <link rel="stylesheet"
        href="{{ asset('assets/fonts/inter/inter.css') }}"
        id="main-font-link" />

    <link rel="stylesheet"
        href="{{ asset('assets/fonts/tabler-icons.min.css') }}" />

    <link rel="stylesheet"
        href="{{ asset('assets/fonts/feather.css') }}" />

    <link rel="stylesheet"
        href="{{ asset('assets/fonts/fontawesome.css') }}" />

    <link rel="stylesheet"
        href="{{ asset('assets/fonts/material.css') }}" />

    <link rel="stylesheet"
        href="{{ asset('assets/css/style.css') }}"
        id="main-style-link" />

    <link rel="stylesheet"
        href="{{ asset('assets/css/style-preset.css') }}" />

    <link rel="stylesheet"
        href="{{ asset('assets/css/landing.css') }}" />


    <style>

        @import url('https://fonts.googleapis.com/css2?family=Cairo:wght@500&display=swap');

        * {
            font-family: 'Cairo', sans-serif;
        }

        #loading-overlay {

            position: fixed;

            top: 0;
            left: 0;

            width: 100%;
            height: 100%;

            background-color: rgba(255, 255, 255, 0.883);

            z-index: 9999;

            display: flex;

            justify-content: center;

            align-items: center;
        }

        .spinner {

            animation: spin 1s linear infinite;
        }

        @keyframes spin {

            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .note-text {

            font-size: 16px;

            white-space: pre-wrap;

            word-break: break-word;

            line-height: 1.8;
        }

        .note-image {

            max-height: 70vh;

            max-width: 100%;

            object-fit: contain;

            border-radius: 8px;
        }

    </style>

</head>


<body class="landing-page">


{{-- =========================================================
     LOADING
========================================================= --}}

<div id="loading-overlay">

    <img src="{{ asset('assets/images/customersLogo.png') }}"
        width="90"
        alt=""
        class="img-fluid spinner">

</div>


{{-- =========================================================
     PRELOADER
========================================================= --}}

<div class="loader-bg">

    <div class="loader-track">

        <div class="loader-fill"></div>

    </div>

</div>


{{-- =========================================================
     HEADER
========================================================= --}}

<header id="home"
    style="
        background-image:
        linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.6)),
        url({{ asset('assets/images/cover3.jpg') }});

        height: 80vh;

        background-size: cover;

        background-position: center;
    ">


    {{-- NAVBAR --}}

    <nav class="navbar navbar-expand-md navbar-light default">

        <div class="container">


            <a class="navbar-brand" href="#">

                <img src="{{ asset('assets/images/customersLogo.png') }}"
                    alt="logo"
                    width="60" />

            </a>


            <button class="navbar-toggler rounded"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarTogglerDemo01"
                aria-controls="navbarTogglerDemo01"
                aria-expanded="false"
                aria-label="Toggle navigation">

                <span class="navbar-toggler-icon"></span>

            </button>


            <div class="collapse navbar-collapse"
                id="navbarTogglerDemo01">


                <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-start">


                    <li class="nav-item px-1">

                        <a class="nav-link"
                            href="#"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#offcanvas_pc_layout">

                            {{ Auth::user()->name }}

                        </a>

                    </li>


                    <li class="nav-item px-1 me-2 mb-2 mb-md-0">

                        <a class="btn btn-icon btn-light-dark"
                            href="#"
                            data-bs-toggle="offcanvas"
                            data-bs-target="#offcanvas_pc_layout">

                            <i class="fas fa-user"></i>

                        </a>

                    </li>


                    <li class="nav-item">

                        <a class="btn btn-primary"
                            href="#"
                            onclick="
                                event.preventDefault();
                                document.getElementById('logout-form').submit();
                            ">

                            تسجيل الخروج

                            <svg class="pc-icon me-2">

                                <use xlink:href="#custom-logout-1-outline"></use>

                            </svg>

                        </a>


                        <form id="logout-form"
                            action="{{ route('customer.logout') }}"
                            method="POST"
                            class="d-none">

                            @csrf

                        </form>

                    </li>

                </ul>

            </div>

        </div>

    </nav>


    {{-- HEADER CONTENT --}}

    <div class="container">

        <div class="row justify-content-center">

            <div class="col-md-10 text-center">


                <h1 class="mb-4 wow fadeInUp text-white"
                    data-wow-delay="0.2s">

                    مرحباً بك

                    <span class="text-primary">
                        {{ Auth::user()->name }}
                    </span>

                    — لوحة التحكم

                </h1>


                <div class="row justify-content-center wow fadeInUp"
                    data-wow-delay="0.3s">

                    <div class="col-md-8">

                        <p class="text-muted f-16 mb-0"
                            style="color: white !important;">

                            أهلاً وسهلاً بك في حسابك.
                            يمكنك متابعة طلباتك،
                            الاطلاع على حالة الشحن،
                            وإدارة بيانات الحساب بسهولة.

                            <strong>
                                نحن نقدر ثقتك ونسعى لتقديم أفضل خدمة دائمًا.
                            </strong>

                        </p>

                    </div>

                </div>


                <div class="my-4 my-sm-5 wow fadeInUp"
                    data-wow-delay="0.4s">

                    <a href="#orders"
                        class="btn btn-outline-primary"
                        style="color: white !important;">

                        عـرض طـلـبـاتـي

                    </a>

                </div>

            </div>

        </div>

    </div>

</header>


{{-- =========================================================
     ORDERS SECTION
========================================================= --}}

<section class="bg-white support-team-block"
    id="orders"
    style="
        background-image:
        url({{ asset('assets/images/landing/img-headerbg.jpg') }});
    ">


@if ($orders->count() == 0)


    {{-- NO ORDERS --}}

    <div class="container title">

        <div class="row justify-content-center text-center wow fadeInUp"
            data-wow-delay="0.2s">


            <div class="col-md-12 col-xl-12">

                <h2>

                    لـيـس لـديـك

                    <span class="text-primary">
                        طـلـبـات
                    </span>

                    حـتـي الان

                </h2>

            </div>


            @if (
                Auth::user()->orders->sum('total_price')
                +
                Auth::user()->orders->sum('ship_price')
                -
                Auth::user()->orders->sum('paid_price')
                > 0
            )

                <div class="col-md-12 col-xl-12">

                    <p class="text-muted f-16"
                        style="color: black !important;">

                        إجـمـالـي مـسـتـحـقـات الـمـتـجـر :

                        <strong class="text-danger"
                            style="font-size: 22px;">

                            {{
                                Auth::user()->orders->sum('total_price')
                                +
                                Auth::user()->orders->sum('ship_price')
                                -
                                Auth::user()->orders->sum('paid_price')
                            }}

                        </strong>

                    </p>

                </div>

            @endif


        </div>

    </div>


@else


    {{-- ORDERS TITLE --}}

    <div class="container title">

        <div class="row justify-content-center text-center wow fadeInUp"
            data-wow-delay="0.2s">


            <div class="col-md-12 col-xl-12">

                <h2>

                    <span class="text-primary">
                        الـطـلـبـيـات
                    </span>

                    الـخـاصـة بـك

                </h2>

            </div>


            @if (
                Auth::user()->orders->sum('total_price')
                +
                Auth::user()->orders->sum('ship_price')
                -
                Auth::user()->orders->sum('paid_price')
                > 0
            )

                <div class="col-md-12 col-xl-12">

                    <p class="text-muted f-16"
                        style="color: black !important;">

                        إجـمـالـي مـسـتـحـقـات الـمـتـجـر :

                        <strong class="text-danger"
                            style="font-size: 22px;">

                            {{
                                Auth::user()->orders->sum('total_price')
                                +
                                Auth::user()->orders->sum('ship_price')
                                -
                                Auth::user()->orders->sum('paid_price')
                            }}

                        </strong>

                    </p>

                </div>

            @endif


        </div>

    </div>


    {{-- =====================================================
         ORDERS TABLE
    ====================================================== --}}

    <div style="padding: 0 30px 0 30px;">

        <div class="row">


            <div class="col-sm-12">

                <div class="card">


                    {{-- SEARCH HEADER --}}

                    <div class="card-header">

                        <a class="btn btn-primary m-t-5"
                            style="margin-right: 10px;"
                            data-bs-toggle="collapse"
                            href="#collapseExample2"
                            role="button"
                            aria-expanded="false"
                            aria-controls="collapseExample2">

                            الـبـحـث

                        </a>

                    </div>


                    {{-- SEARCH FORM --}}

                    <div class="collapse"
                        id="collapseExample2">

                        <div class="card-body">


                            <form action="{{ route('customer.order.search') }}"
                                method="get">

                                @csrf


                                <div class="row">


                                    @if (isset($query))


                                        <input type="hidden"
                                            name="page"
                                            value="{{ $query['page'] ?? '' }}">


                                        <div class="col-md-4">

                                            <div class="form-group">

                                                <label class="form-label">

                                                    كود الطلبية

                                                </label>

                                                <input type="text"
                                                    name="code"
                                                    value="{{ $query['code'] ?? '' }}"
                                                    class="form-control"
                                                    placeholder="كود الطلبية">

                                            </div>

                                        </div>


                                    @else


                                        <input type="hidden"
                                            value="{{ $page }}"
                                            name="page">


                                        <div class="col-md-4">

                                            <div class="form-group">

                                                <label class="form-label">

                                                    كود الطلبية

                                                </label>

                                                <input type="text"
                                                    name="code"
                                                    class="form-control"
                                                    placeholder="كود الطلبية">

                                            </div>

                                        </div>


                                    @endif


                                    <div class="col-md-2">

                                        <div class="form-group pt-4">

                                            <button type="submit"
                                                class="btn btn-primary p-3">

                                                <i class="fas fa-search"></i>

                                            </button>

                                        </div>

                                    </div>


                                    <div class="col-md-2">

                                        <div class="form-group pt-4">

                                            <a href="{{ route('customer.dashboard') }}"
                                                class="btn btn-primary p-3">

                                                <i class="fas fa-redo"></i>

                                            </a>

                                        </div>

                                    </div>


                                </div>

                            </form>


                        </div>

                    </div>


                    {{-- TABLE BODY --}}

                    <div class="card-body">


                        <div class="dt-responsive table-responsive">

                            <div class="dataTables_wrapper dt-bootstrap5">


                                <div class="row dt-row">

                                    <div class="col-sm-12">


                                        <table class="table table-hover">


                                            {{-- TABLE HEAD --}}

                                            <thead style="background: #7cbbfa;">

                                                <tr>

                                                    <th>#</th>

                                                    <th>كود الطلب</th>

                                                    <th>الزبون</th>

                                                    <th>نوع الطرد</th>

                                                    <th>حالة الطلب</th>

                                                    <th>المنتجات</th>

                                                    <th>كمية القطع</th>

                                                    <th>عدد الطرود</th>

                                                    <th>اجمالي سعر البضاعة</th>

                                                    <th>القيمة المتبقية</th>

                                                    <th>وزن البضاعة وسعر الشحن</th>

                                                    <th>الرحلة</th>

                                                    <th>ملاحظات</th>

                                                </tr>

                                            </thead>


                                            {{-- =================================================
                                                 TABLE BODY
                                            ================================================== --}}

                                            <tbody>


                                            @foreach ($orders as $index => $order)


                                                {{-- Latest note --}}

                                                @php

                                                    $latestOrderNote = $order->notes
                                                        ? $order->notes->sortByDesc('created_at')->first()
                                                        : null;

                                                @endphp


                                                <tr>


                                                    {{-- INDEX --}}

                                                    <td>

                                                        {{ $orders->firstItem() + $index }}

                                                    </td>


                                                    {{-- ORDER CODE --}}

                                                    <td>

                                                        {{ $order->code }}

                                                        <br>

                                                        {{ $order->platform->name }}

                                                    </td>


                                                    {{-- CUSTOMER --}}

                                                    <td>

                                                        {{ $order->customer->name }}

                                                        <br>

                                                        <a style="color: green"
                                                            href="https://wa.me/{{ $order->customer->phone_1 }}"
                                                            target="_blank">

                                                            {{ $order->customer->code }}

                                                        </a>

                                                    </td>


                                                    {{-- ORDER TYPE --}}

                                                    <td>

                                                        {{ $order->order_type ?: '-' }}

                                                    </td>


                                                    {{-- STATUS --}}

                                                    @if ($order->status == 'paid_full')

                                                        <td class="text-info">

                                                            مدفوعة

                                                            @if ($order->delivered)

                                                                <br>

                                                                <span class="text-success">

                                                                    تم التسليم
                                                                    {{ $order->delivered_at }}

                                                                </span>

                                                            @elseif ($order->arrived)

                                                                <br>

                                                                <span class="text-primary">

                                                                    وصلت

                                                                </span>

                                                                -

                                                                <span class="text-primary">

                                                                    {{ $order->arrived_at }}

                                                                </span>

                                                            @endif

                                                        </td>


                                                    @elseif ($order->status == 'unpaid')

                                                        <td class="text-warning">

                                                            غير مدفوعة

                                                            @if ($order->delivered)

                                                                <br>

                                                                <span class="text-success">

                                                                    تم التسليم
                                                                    {{ $order->delivered_at }}

                                                                </span>

                                                            @elseif ($order->arrived)

                                                                <br>

                                                                <span class="text-primary">

                                                                    وصلت

                                                                </span>

                                                                -

                                                                <span class="text-primary">

                                                                    {{ $order->arrived_at }}

                                                                </span>

                                                            @endif

                                                        </td>


                                                    @elseif ($order->status == 'paid')

                                                        <td class="text-info">

                                                            مدفوعة من غير الشحن

                                                            @if ($order->delivered)

                                                                <br>

                                                                <span class="text-success">

                                                                    تم التسليم
                                                                    {{ $order->delivered_at }}

                                                                </span>

                                                            @elseif ($order->arrived)

                                                                <br>

                                                                <span class="text-primary">

                                                                    وصلت

                                                                </span>

                                                                -

                                                                <span class="text-primary">

                                                                    {{ $order->arrived_at }}

                                                                </span>

                                                            @endif

                                                        </td>


                                                    @elseif ($order->status == 'canceld')

                                                        <td class="text-danger">

                                                            ملغية

                                                            @if ($order->delivered)

                                                                <br>

                                                                <span class="text-success">

                                                                    تم التسليم
                                                                    {{ $order->delivered_at }}

                                                                </span>

                                                            @elseif ($order->arrived)

                                                                <br>

                                                                <span class="text-primary">

                                                                    وصلت

                                                                </span>

                                                                -

                                                                <span class="text-primary">

                                                                    {{ $order->arrived_at }}

                                                                </span>

                                                            @endif

                                                        </td>


                                                    @elseif ($order->status == 'delivered')

                                                        <td class="text-success">

                                                            تم التسليم

                                                            @if ($order->delivered)

                                                                <br>

                                                                <span class="text-success">

                                                                    تم التسليم
                                                                    {{ $order->delivered_at }}

                                                                </span>

                                                            @elseif ($order->arrived)

                                                                <br>

                                                                <span class="text-primary">

                                                                    وصلت

                                                                </span>

                                                                -

                                                                <span class="text-primary">

                                                                    {{ $order->arrived_at }}

                                                                </span>

                                                            @endif

                                                        </td>

                                                    @endif


                                                    {{-- PRODUCTS --}}

                                                    <td>

                                                        @if ($order->items->count() > 0)

                                                            <button type="button"
                                                                class="btn btn-light-info"
                                                                data-bs-toggle="modal"
                                                                data-bs-target="#exampleModalOrderItems{{ $order->id }}">

                                                                المنتجات

                                                                {{ $order->items->count() }}

                                                            </button>

                                                        @else

                                                            -

                                                        @endif

                                                    </td>


                                                    {{-- QUANTITY --}}

                                                    <td>

                                                        {{ $order->qty }}

                                                    </td>


                                                    {{-- BAGS --}}

                                                    <td>

                                                        <span style="color: blue;">

                                                            {{ $order->bags_num }}

                                                        </span>


                                                        @if ($order->bags_qty)

                                                            <br>

                                                            الكمية في الطرود

                                                            <span style="color: blue;">

                                                                {{ $order->bags_qty }}

                                                            </span>

                                                        @endif

                                                    </td>


                                                    {{-- TOTAL PRICE --}}

                                                    <td style="color: green;">

                                                        {{ $order->total_price }}

                                                        {{ $order->currency_sign }}

                                                    </td>


                                                    {{-- REMAINING --}}

                                                    <td class="text-warning">

                                                        <span>

                                                            {{
                                                                $order->total_price
                                                                +
                                                                $order->ship_price
                                                                -
                                                                $order->paid_price
                                                            }}

                                                            {{ $order->currency_sign }}

                                                        </span>


                                                        @if ($order->paids->count() > 0)

                                                            <br>

                                                            <span class="text-secondary">

                                                                {{ $order->paids->last()->paymentMethod->name }}

                                                            </span>

                                                        @endif

                                                    </td>


                                                    {{-- SHIPPING --}}

                                                    <td>

                                                        @if ($order->kilo)

                                                            ({{ $order->kilo }} KG)

                                                            <br>

                                                        @endif


                                                        @if ($order->ship_price)

                                                            إجمالي سعر الشحن

                                                            <span style="color: green;">

                                                                {{ $order->ship_price }}

                                                                {{ $order->currency_sign }}

                                                            </span>

                                                        @endif


                                                        @if (!$order->ship_price && !$order->kilo)

                                                            -

                                                        @endif

                                                    </td>


                                                    {{-- TRIP --}}

                                                    <td>

                                                        @if ($order->trip)

                                                            {{ $order->trip->code }}

                                                            -

                                                            {{ $order->trip_details }}

                                                        @elseif ($order->trip_details)

                                                            {{ $order->trip_details }}

                                                        @else

                                                            -

                                                        @endif


                                                        <br>


                                                        <span class="text-primary">

                                                            {{ $order->trip_status }}

                                                        </span>

                                                    </td>


                                                    {{-- =================================================
                                                         NOTES
                                                    ================================================== --}}

                                                    <td>

                                                        <button type="button"
                                                            class="btn btn-light-warning"
                                                            data-bs-toggle="offcanvas"
                                                            data-bs-target="#customerNotesOffcanvas{{ $order->id }}">

                                                            @if ($order->note)

                                                                {{ Str::words($order->note, 1, '...') }}

                                                            @elseif ($latestOrderNote)

                                                                {{ Str::words($latestOrderNote->note, 1, '...') }}

                                                            @elseif ($order->trip_note)

                                                                {{ Str::words($order->trip_note, 1, '...') }}

                                                            @else

                                                                ملاحظات

                                                            @endif

                                                        </button>


                                                        {{-- =================================================
                                                             PRODUCT MODAL
                                                        ================================================== --}}

                                                        @if ($order->items->count() > 0)

                                                            <div class="modal fade"
                                                                id="exampleModalOrderItems{{ $order->id }}"
                                                                tabindex="-1"
                                                                aria-hidden="true">

                                                                <div class="modal-dialog modal-dialog-centered modal-lg">

                                                                    <div class="modal-content">


                                                                        <div class="modal-body text-center">

                                                                            <div class="container">


                                                                                <div>

                                                                                    <i class="fas fa-box-open text-primary fa-5x mb-3"></i>

                                                                                </div>


                                                                                <p style="
                                                                                    font-size: 18px;
                                                                                    word-wrap: break-word;
                                                                                ">

                                                                                    منتجات الطلبية

                                                                                    <span class="text-primary">

                                                                                        {{ $order->code }}

                                                                                    </span>

                                                                                    <br>

                                                                                    للزبون

                                                                                    <span class="text-primary">

                                                                                        {{ $order->customer->name }}

                                                                                    </span>

                                                                                </p>


                                                                                <div class="table-responsive">

                                                                                    <table class="table table-bordered">

                                                                                        <thead>

                                                                                            <tr>

                                                                                                <th>اسم المنتج</th>

                                                                                                <th>الكمية</th>

                                                                                                <th>السعر</th>

                                                                                            </tr>

                                                                                        </thead>


                                                                                        <tbody>


                                                                                        @foreach ($order->items as $item)

                                                                                            <tr>

                                                                                                <td style="font-size: 16px;">

                                                                                                    {{ $item->name }}

                                                                                                </td>

                                                                                                <td style="font-size: 16px;">

                                                                                                    {{ $item->quantity }}

                                                                                                </td>

                                                                                                <td style="font-size: 16px;">

                                                                                                    {{ $item->price }}

                                                                                                    {{ $order->currency_sign }}

                                                                                                </td>

                                                                                            </tr>

                                                                                        @endforeach


                                                                                        </tbody>

                                                                                    </table>

                                                                                </div>


                                                                                <div class="modal-footer d-flex justify-content-center">

                                                                                    <button type="button"
                                                                                        class="btn btn-secondary"
                                                                                        data-bs-dismiss="modal">

                                                                                        إغـلاق

                                                                                    </button>

                                                                                </div>


                                                                            </div>

                                                                        </div>

                                                                    </div>

                                                                </div>

                                                            </div>

                                                        @endif


                                                        {{-- =================================================
                                                             CUSTOMER NOTES OFFCANVAS
                                                        ================================================== --}}

                                                        <div class="offcanvas border-0 pct-offcanvas offcanvas-end"
                                                            tabindex="-1"
                                                            id="customerNotesOffcanvas{{ $order->id }}"
                                                            aria-labelledby="customerNotesOffcanvasLabel{{ $order->id }}">


                                                            <div class="offcanvas-header">


                                                                <h5 class="offcanvas-title"
                                                                    id="customerNotesOffcanvasLabel{{ $order->id }}">

                                                                    كـل الـمـلاحـظـات

                                                                </h5>


                                                                <button type="button"
                                                                    class="btn btn-icon btn-link-danger"
                                                                    data-bs-dismiss="offcanvas"
                                                                    aria-label="Close">

                                                                    <i class="ti ti-x"></i>

                                                                </button>


                                                            </div>


                                                            <div class="pct-body"
                                                                style="height: calc(100% - 85px)">


                                                                <div class="offcanvas-body py-0">


                                                                    <ul class="list-group list-group-flush">


                                                                        <li class="list-group-item">


                                                                            <div class="pc-dark">


                                                                                {{-- ORDER CODE --}}

                                                                                <div class="form-group mb-4">

                                                                                    <h6>

                                                                                        الـطـلـب :

                                                                                        <span class="text-primary">

                                                                                            {{ $order->code }}

                                                                                        </span>

                                                                                    </h6>

                                                                                </div>


                                                                                {{-- TITLE --}}

                                                                                <div class="d-flex justify-content-between align-items-center mb-3">

                                                                                    <h5 class="mb-0">

                                                                                        <i class="fas fa-history text-primary"></i>

                                                                                        سجل الملاحظات

                                                                                    </h5>

                                                                                </div>


                                                                                {{-- =================================================
                                                                                     ORIGINAL ORDER NOTE
                                                                                ================================================== --}}

                                                                                @if ($order->note)

                                                                                    <div class="card mb-3 shadow-sm">

                                                                                        <div class="card-body">

                                                                                            <div class="text-center">


                                                                                                <h6 class="mb-3 note-text">

                                                                                                    <i class="fas fa-comment-alt text-primary"></i>

                                                                                                    {{ $order->note }}

                                                                                                </h6>


                                                                                                <small class="text-muted">

                                                                                                    <i class="far fa-calendar-alt"></i>

                                                                                                    {{ $order->updated_at->format('Y-m-d') }}

                                                                                                    <br>

                                                                                                    <i class="far fa-clock"></i>

                                                                                                    {{ $order->updated_at->format('H:i') }}

                                                                                                </small>


                                                                                            </div>

                                                                                        </div>

                                                                                    </div>

                                                                                @endif


                                                                                {{-- =================================================
                                                                                     PREVIOUS NOTES
                                                                                ================================================== --}}

                                                                                @if ($order->notes && $order->notes->count() > 0)


                                                                                    @foreach ($order->notes->sortByDesc('created_at') as $orderNote)


                                                                                        <div class="card mb-3 shadow-sm">


                                                                                            <div class="card-body">


                                                                                                <div class="text-center">


                                                                                                    {{-- NOTE TEXT --}}

                                                                                                    <h6 class="mb-3 note-text">

                                                                                                        <i class="fas fa-comment-alt text-primary"></i>

                                                                                                        {{ $orderNote->note }}

                                                                                                    </h6>


                                                                                                    {{-- DATE/TIME --}}

                                                                                                    <small class="text-muted">

                                                                                                        <i class="far fa-calendar-alt"></i>

                                                                                                        {{ $orderNote->created_at->format('Y-m-d') }}

                                                                                                        <br>

                                                                                                        <i class="far fa-clock"></i>

                                                                                                        {{ $orderNote->created_at->format('H:i') }}

                                                                                                    </small>


                                                                                                    {{-- =================================================
                                                                                                         NOTE IMAGE BUTTON
                                                                                                    ================================================== --}}

                                                                                                    @if ($orderNote->image)


                                                                                                        <div class="mt-3">


                                                                                                            <button type="button"
                                                                                                                class="btn btn-light-info"
                                                                                                                data-bs-toggle="modal"
                                                                                                                data-bs-target="#noteImageModal{{ $orderNote->id }}">


                                                                                                                <i class="fas fa-image"></i>


                                                                                                                عرض صورة الملاحظة


                                                                                                            </button>


                                                                                                        </div>


                                                                                                    @endif


                                                                                                </div>

                                                                                            </div>

                                                                                        </div>


                                                                                        {{-- =================================================
                                                                                             NOTE IMAGE MODAL
                                                                                        ================================================== --}}

                                                                                        @if ($orderNote->image)


                                                                                            <div class="modal fade"
                                                                                                id="noteImageModal{{ $orderNote->id }}"
                                                                                                tabindex="-1"
                                                                                                aria-labelledby="noteImageModalLabel{{ $orderNote->id }}"
                                                                                                aria-hidden="true">


                                                                                                <div class="modal-dialog modal-dialog-centered modal-lg">


                                                                                                    <div class="modal-content">


                                                                                                        <div class="modal-header">


                                                                                                            <h5 class="modal-title"
                                                                                                                id="noteImageModalLabel{{ $orderNote->id }}">

                                                                                                                صورة الملاحظة

                                                                                                            </h5>


                                                                                                            <button type="button"
                                                                                                                class="btn-close"
                                                                                                                data-bs-dismiss="modal"
                                                                                                                aria-label="Close">

                                                                                                            </button>


                                                                                                        </div>


                                                                                                        <div class="modal-body text-center">


                                                                                                            <img src="{{ asset('uploads/order_notes/' . $orderNote->image) }}"
                                                                                                                class="img-fluid note-image"
                                                                                                                alt="صورة الملاحظة">


                                                                                                        </div>


                                                                                                        <div class="modal-footer justify-content-center">


                                                                                                            <button type="button"
                                                                                                                class="btn btn-secondary"
                                                                                                                data-bs-dismiss="modal">

                                                                                                                إغلاق

                                                                                                            </button>


                                                                                                        </div>


                                                                                                    </div>


                                                                                                </div>


                                                                                            </div>

                                                                                        @endif


                                                                                    @endforeach


                                                                                @endif


                                                                                {{-- =================================================
                                                                                     NO ORDER NOTES
                                                                                ================================================== --}}

                                                                                @if (!$order->note && (!$order->notes || $order->notes->count() == 0))


                                                                                    <div class="alert alert-info text-center">

                                                                                        <i class="fas fa-info-circle"></i>

                                                                                        لا توجد ملاحظات لهذه الطلبية.

                                                                                    </div>

                                                                                @endif


                                                                                {{-- =================================================
                                                                                     TRIP NOTE
                                                                                     DO NOT REMOVE
                                                                                ================================================== --}}

                                                                                @if ($order->trip_note)


                                                                                    <div class="card mb-3 shadow-sm">


                                                                                        <div class="card-body">


                                                                                            <div class="text-center">


                                                                                                <h6 class="mb-3">

                                                                                                    <i class="fas fa-plane text-primary"></i>

                                                                                                    ملاحظات الرحلة

                                                                                                </h6>


                                                                                                <p class="note-text mb-0">

                                                                                                    {!! nl2br(e(wordwrap($order->trip_note, 100, "\n", true))) !!}

                                                                                                </p>


                                                                                            </div>


                                                                                        </div>


                                                                                    </div>


                                                                                @endif


                                                                            </div>

                                                                        </li>


                                                                        <li class="list-group-item">

                                                                            <div class="theme-color preset-color">

                                                                                <a href="#!"
                                                                                    data-bs-dismiss="offcanvas"
                                                                                    class="active"
                                                                                    data-value="preset-1">

                                                                                    <i class="ti ti-check"></i>

                                                                                </a>

                                                                            </div>

                                                                        </li>


                                                                    </ul>


                                                                </div>

                                                            </div>

                                                        </div>


                                                    </td>


                                                </tr>


                                            @endforeach


                                            </tbody>


                                            {{-- =================================================
                                                 FOOTER
                                            ================================================== --}}

                                            <tfoot style="background: #85bdf5;">

                                                <tr>

                                                    <th>#</th>

                                                    <th>كود الطلب</th>

                                                    <th>الزبون</th>

                                                    <th>نوع الطرد</th>

                                                    <th>حالة الطلب</th>

                                                    <th>المنتجات</th>

                                                    <th>كمية القطع</th>

                                                    <th>عدد الطرود</th>

                                                    <th>اجمالي سعر البضاعة</th>

                                                    <th>القيمة المتبقية</th>

                                                    <th>وزن البضاعة وسعر الشحن</th>

                                                    <th>الرحلة</th>

                                                    <th>ملاحظات</th>

                                                </tr>

                                            </tfoot>


                                        </table>


                                        {{-- PAGINATION --}}

                                        @if (isset($query))

                                            <span id="paginate">

                                                {{ $orders->appends($query)->links('pagination::bootstrap-4') }}

                                            </span>

                                        @else

                                            <span id="paginate">

                                                {{ $orders->links('pagination::bootstrap-4') }}

                                            </span>

                                        @endif


                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>


@endif

</section>


{{-- =========================================================
     CONTACT US
========================================================= --}}

<section class="bg-white">

    <div class="container title mb-0">

        <div class="row align-items-center wow fadeInUp"
            data-wow-delay="0.2s">


            <div class="col-md-8">

                <h2 class="mb-3">

                    <span class="text-primary">
                        تواصل
                    </span>

                    معنا

                </h2>


                <p class="text-muted mb-md-0">

                    يسعدنا تواصلك معنا عبر القنوات التالية
                    للحصول على الدعم أو الاستفسارات.

                </p>

            </div>


            <div class="col-md-4 text-md-end">


                <a class="btn btn-lg btn-outline-primary bg-gray-100 me-2"
                    href="https://www.facebook.com/people/%D8%B4%D8%B1%D9%83%D8%A9-Shop-And-Go-%D9%84%D9%84%D8%B4%D8%B1%D8%A7%D8%A1-%D9%88-%D8%A7%D9%84%D8%B4%D8%AD%D9%86/61550810591211/?locale=da_DK#"
                    target="_blank"
                    title="تواصل عبر فيسبوك">

                    <i class="ti ti-brand-facebook"></i>

                    فيسبوك

                </a>


                <a class="btn btn-lg btn-success"
                    href="https://api.whatsapp.com/send?phone=%2B218920403444"
                    target="_blank"
                    title="تواصل عبر واتساب">

                    <i class="ti ti-brand-whatsapp"></i>

                    واتساب

                </a>


            </div>

        </div>

    </div>

</section>


{{-- =========================================================
     FOOTER
========================================================= --}}

<footer class="footer"
    style="
        background-image:
        url({{ asset('assets/images/landing/img-headerbg.jpg') }});
    ">


    <div class="container border-bottom"
        style="margin-top: -60px;">

        <div class="row align-items-center">


            <div class="col my-1 wow fadeInUp"
                data-wow-delay="0.4s">

                <p>

                    © Handcrafted by Team

                    <a href="https://www.linkedin.com/in/malik-alnabouli-a0863a1a4"
                        target="_blank">

                        Malik Al Nabouli

                    </a>

                </p>

            </div>


            <div class="col-auto my-1">

                <ul class="list-inline footer-sos-link">

                    <li class="list-inline-item wow fadeInUp"
                        data-wow-delay="0.4s">

                        <a href="https://www.facebook.com/people/%D8%B4%D8%B1%D9%83%D8%A9-Shop-And-Go-%D9%84%D9%84%D8%B4%D8%B1%D8%A7%D8%A1-%D9%88-%D8%A7%D9%84%D8%B4%D8%AD%D9%86/61550810591211/"
                            target="_blank">

                            <svg class="pc-icon">

                                <use xlink:href="#custom-facebook"></use>

                            </svg>

                        </a>

                    </li>

                </ul>

            </div>


        </div>

    </div>

</footer>


{{-- =========================================================
     CHANGE PASSWORD BUTTON
========================================================= --}}

<div class="pct-c-btn">

    <a href="#"
        data-bs-toggle="offcanvas"
        data-bs-target="#offcanvas_pc_layout">

        <svg class="pc-icon">

            <use xlink:href="#custom-setting-2"></use>

        </svg>

    </a>

</div>


{{-- =========================================================
     CHANGE PASSWORD OFFCANVAS
========================================================= --}}

<div class="offcanvas border-0 pct-offcanvas offcanvas-end"
    tabindex="-1"
    id="offcanvas_pc_layout">


    <div class="offcanvas-header">

        <h5 class="offcanvas-title">

            تغيير كلمة المرور

        </h5>


        <button type="button"
            class="btn btn-icon btn-link-danger"
            data-bs-dismiss="offcanvas"
            aria-label="Close">

            <i class="ti ti-x"></i>

        </button>

    </div>


    <div class="pct-body"
        style="height: calc(100% - 85px)">


        <div class="offcanvas-body py-0">


            <ul class="list-group list-group-flush">


                <li class="list-group-item">


                    <div class="pc-dark">


                        <form method="POST"
                            action="{{ route('customer.change.password.new') }}">

                            @csrf


                            <input type="hidden"
                                name="customer_id"
                                value="{{ Auth::guard('customer')->user()->id }}">


                            <div class="form-group mb-3">

                                <input type="password"
                                    name="current_password"
                                    class="form-control"
                                    required
                                    placeholder="كلمة المرور الحالية">

                            </div>


                            <div class="form-group mb-3">

                                <input type="password"
                                    name="new_password"
                                    class="form-control"
                                    required
                                    placeholder="كلمة المرور الجديدة">

                            </div>


                            <div class="form-group mb-3">

                                <input type="password"
                                    name="new_confirm_password"
                                    class="form-control"
                                    required
                                    placeholder="إعادة كلمة المرور الجديدة">

                            </div>


                            <div class="d-grid mt-4">

                                <button type="submit"
                                    class="btn btn-primary"
                                    style="
                                        background-color: #282E4D;
                                        border-color:#282E4D;
                                    ">

                                    حفظ كلمة المرور الجديدة

                                </button>

                            </div>


                        </form>


                    </div>

                </li>


                <li class="list-group-item">

                    <div class="theme-color preset-color">

                        <a href="#!"
                            class="active"
                            data-value="preset-1">

                            <i class="ti ti-check"></i>

                        </a>

                    </div>

                </li>


            </ul>

        </div>

    </div>

</div>


{{-- =========================================================
     JAVASCRIPT
========================================================= --}}

<script src="https://code.jquery.com/jquery-3.6.1.min.js"></script>

<script src="{{ asset('assets/js/plugins/popper.min.js') }}"></script>

<script src="{{ asset('assets/js/plugins/simplebar.min.js') }}"></script>

<script src="{{ asset('assets/js/plugins/bootstrap.min.js') }}"></script>

<script src="{{ asset('assets/js/fonts/custom-font.js') }}"></script>

<script src="{{ asset('assets/js/config.js') }}"></script>

<script src="{{ asset('assets/js/pcoded.js') }}"></script>

<script src="{{ asset('assets/js/plugins/feather.min.js') }}"></script>

<script src="{{ asset('assets/js/plugins/wow.min.js') }}"></script>

<script src="https://cdn.jsdelivr.net/jquery.marquee/1.4.0/jquery.marquee.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>

<script src="{{ asset('assets/js/plugins/Jarallax.js') }}"></script>


<script>


    // =========================================================
    // MENU HIDE / SHOW ON SCROLL
    // =========================================================

    let ost = 0;


    document.addEventListener('scroll', function () {


        let cOst = document.documentElement.scrollTop;


        if (cOst == 0) {

            document.querySelector('.navbar')
                .classList.add('top-nav-collapse');

        }

        else if (cOst > ost) {

            document.querySelector('.navbar')
                .classList.add('top-nav-collapse');

            document.querySelector('.navbar')
                .classList.remove('default');

        }

        else {

            document.querySelector('.navbar')
                .classList.add('default');

            document.querySelector('.navbar')
                .classList.remove('top-nav-collapse');

        }


        ost = cOst;

    });


    // =========================================================
    // WOW
    // =========================================================

    var wow = new WOW({

        animateClass: 'animated'

    });

    wow.init();


    // =========================================================
    // OWL CAROUSEL
    // =========================================================

    $('.screen-slide').owlCarousel({

        loop: true,

        margin: 30,

        center: true,

        nav: false,

        dotsContainer: '.app_dotsContainer',

        URLhashListener: true,

        items: 1

    });


    $('.workspace-slider').owlCarousel({

        loop: true,

        margin: 30,

        center: true,

        nav: false,

        dotsContainer: '.workspace-card-block',

        URLhashListener: true,

        items: 1.5

    });


    // =========================================================
    // MARQUEE
    // =========================================================

    $('.marquee').marquee({

        duration: 500000,

        pauseOnHover: true,

        startVisible: true,

        duplicated: true

    });


    $('.marquee-1').marquee({

        duration: 500000,

        pauseOnHover: true,

        startVisible: true,

        duplicated: true,

        direction: 'right'

    });


    // =========================================================
    // LOADING OVERLAY
    // =========================================================

    window.addEventListener('load', function () {

        var loadingOverlay =
            document.getElementById('loading-overlay');

        if (loadingOverlay) {

            loadingOverlay.style.display = 'none';

        }

    });


</script>


</body>

</html>
```
