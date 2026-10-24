<header class="pc-header">
    <div class="header-wrapper"> <!-- [Mobile Media Block] start -->
        <div class="me-auto pc-mob-drp">
            <ul class="list-unstyled">
                <!-- ======= Menu collapse Icon ===== -->
                <li class="pc-h-item pc-sidebar-collapse">
                    <a href="#" class="pc-head-link ms-0" id="sidebar-hide">
                        <i class="ti ti-menu-2"></i>
                    </a>
                </li>
                <li class="pc-h-item pc-sidebar-popup">
                    <a href="#" class="pc-head-link ms-0" id="mobile-collapse">
                        <i class="ti ti-menu-2"></i>
                    </a>
                </li>

                {{-- <li class="dropdown pc-h-item">
                    <a class="pc-head-link dropdown-toggle arrow-none m-0 trig-drp-search" data-bs-toggle="dropdown"
                        href="#" role="button" aria-haspopup="false" aria-expanded="false">
                        <svg class="pc-icon">
                            <use xlink:href="#custom-search-normal-1"></use>
                        </svg>
                    </a>
                    <div class="dropdown-menu pc-h-dropdown drp-search">

                        <form action="{{ route('voucher.create') }}" method="get" class="px-3 py-2">
                            @csrf
                            <input type="search" name="search" class="form-control border-0 shadow-none"
                                placeholder="البحث هنا..." required />
                        </form>

                    </div>
                </li> --}}

            </ul>
        </div>


        <!-- [Mobile Media Block end] -->
        <div class="ms-auto">
            <ul class="list-unstyled">


        @can('treasury-list')
                <li class="pc-h-item">
                    @if (auth()->user()->safe() < 0)
                    <a class="pc-link text-danger" href="{{ route('safe.history',['currency' => 'dinar']) }}">
                        <span class="pc-micon">
                            <i class="fas fa-suitcase" style="font-size: 22px;"></i></span>
                            <span class="text-danger" style="padding-right: 5px;"> {{ auth()->user()->safe() }} د.ل </span>
                    @else
                    <a class="pc-link text-info" href="{{ route('safe.history',['currency' => 'dinar']) }}">
                        <span class="pc-micon">
                            <i class="fas fa-suitcase" style="font-size: 22px;"></i></span>
                            <span class="text-info" style="padding-right: 5px;"> {{ auth()->user()->safe() }} د.ل </span>
                    @endif
                    </a>
                </li>

                <li class="pc-h-item" style="padding-right: 15px;">
                    @if (auth()->user()->safe() < 0)
                    <a class="pc-link text-danger" href="{{ route('safe.history',['currency' => 'dollar']) }}">
                        <span class="pc-micon">
                            <i class="fas fa-suitcase" style="font-size: 22px;"></i></span>
                            <span class="text-danger" style="padding-right: 5px;"> {{ auth()->user()->safeDollar() }} $ </span>
                    @else
                    <a class="pc-link text-info" href="{{ route('safe.history',['currency' => 'dollar']) }}">
                        <span class="pc-micon">
                            <i class="fas fa-suitcase" style="font-size: 22px;"></i></span>
                            <span class="text-info" style="padding-right: 5px;"> {{ auth()->user()->safeDollar() }} $ </span>
                    @endif
                    </a>
                </li>

                <li class="pc-h-item" style="padding-right: 15px;">
                    @if (auth()->user()->safe() < 0)
                    <a class="pc-link text-danger" href="{{ route('safe.history',['currency' => 'euro']) }}">
                        <span class="pc-micon">
                            <i class="fas fa-suitcase" style="font-size: 22px;"></i></span>
                            <span class="text-danger" style="padding-right: 5px;"> {{ auth()->user()->safeEuro() }} € </span>
                    @else
                    <a class="pc-link text-info" href="{{ route('safe.history',['currency' => 'euro']) }}">
                        <span class="pc-micon">
                            <i class="fas fa-suitcase" style="font-size: 22px;"></i></span>
                            <span class="text-info" style="padding-right: 5px;"> {{ auth()->user()->safeEuro() }} € </span>
                    @endif
                    </a>
                </li>

                @endcan

                <li class="dropdown pc-h-item">
                    <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false" aria-expanded="false">
                        <svg class="pc-icon">
                            <use xlink:href="#custom-notification"></use>
                        </svg>
                        @if (auth()->user()->notifications()->where('read', false)->count() > 0)
                            <span
                                class="badge bg-success pc-h-badge">{{ auth()->user()->notifications()->where('read', false)->count() }}</span>
                        @endif
                    </a>

                    @if ($main_notifications->count() > 0)
                        <div class="dropdown-menu dropdown-notification dropdown-menu-end pc-h-dropdown">
                            <div class="dropdown-header d-flex align-items-center justify-content-between">
                                <h5 class="m-0">الاشعارات الواردة</h5>
                                {{-- <a href="#" class="btn btn-link btn-sm">حدد الكل كمقروءة</a> --}}
                            </div>
                            <div class="dropdown-body text-wrap header-notification-scroll position-relative"
                                style="max-height: calc(100vh - 215px)">
                                <p class="text-span">أخر الاشعارات</p>


                                @foreach ($main_notifications as $notification)
                                    <div class="card mb-2">
                                        <div class="card-body">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0">
                                                    <svg class="pc-icon text-primary">
                                                        <use xlink:href="#custom-message-2"></use>
                                                    </svg>
                                                </div>
                                                <div class="flex-grow-1 ms-3">
                                                    <span
                                                        class="float-end text-sm text-muted">{{ $notification->notification->created_at->diffForHumans() }}</span>
                                                    <h5 class="text-body mb-2">{{ $notification->notification->title }}
                                                    </h5>
                                                    <p class="mb-2"> {{ $notification->notification->disc }}</p>
                                                    @if ($notification->notification->img)
                                                        <a href=".returnOrder" data-bs-toggle="modal"
                                                            class="btn btn-sm btn-primary"
                                                            onclick="showAdImage({{ $notification->notification }})">عرض
                                                            صورة الاشعار</a>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                            </div>
                            <div class="text-center py-2">
                                <a href="{{ route('notification.index') }}" class="link-primary">عرض كل
                                    الاشعارات</a>
                            </div>
                        </div>
                    @endif
                </li>

                <li class="dropdown pc-h-item header-user-profile">
                    <a class="pc-head-link dropdown-toggle arrow-none me-0" data-bs-toggle="dropdown" href="#"
                        role="button" aria-haspopup="false" data-bs-auto-close="outside" aria-expanded="false">
                        <img src="{{ asset('assets/images/user/avatar-1.png') }}" alt="user-image" class="user-avtar" />
                    </a>

                    <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown">
                        <div class="dropdown-header d-flex align-items-center justify-content-between">
                            <h5 class="m-0">الملف الشخصي</h5>
                        </div>
                        <div class="dropdown-body">
                            <div class="profile-notification-scroll position-relative"
                                style="max-height: calc(100vh - 225px)">
                                <div class="d-flex mb-1">
                                    <div class="flex-shrink-0">
                                        <img src="{{ asset('assets/images/user/avatar-1.png') }}" alt="user-image"
                                            class="user-avtar wid-35" />
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">{{ auth()->user()->name }}</h6>
                                        <span>{{ auth()->user()->email }}</span>
                                    </div>
                                </div>
                                <hr class="border-secondary border-opacity-50" />


                                @can('system-create')
                                <div class="card">
                                    <div class="card-body py-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <h5 class="mb-0 d-inline-flex align-items-center">
                                                <i class="pc-icon text-muted me-2 ti ti-power"
                                                    style="font-size: 23px;"></i>
                                                النظام
                                            </h5>
                                            <div class="form-check form-switch form-check-reverse m-0">
                                                <form id="logout-form22" action="{{ route('system.active') }}"
                                                    method="post" class="d-none">
                                                    @csrf
                                                </form>
                                                @if (config('system.active'))
                                                <input class="form-check-input f-18" onclick="event.preventDefault(); document.getElementById('logout-form22').submit();" type="checkbox" checked role="switch" />
                                            @else
                                                <input class="form-check-input f-18" onclick="event.preventDefault(); document.getElementById('logout-form22').submit();" type="checkbox" role="switch" />
                                            @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endcan

                                <hr class="border-secondary border-opacity-50" />
                                <div class="d-grid mb-3">
                                    <a class=" text-center" style="color: grey;" href=".changePassword"
                                    data-bs-toggle="modal">تغيير كلمة المرور</a>
                                </div>
                                <hr class="border-secondary border-opacity-50" />


                                <div class="d-grid mb-3">

                                    <button class="btn btn-primary"onclick="event.preventDefault();
                document.getElementById('logout-form').submit();">
                                        <svg class="pc-icon me-2">
                                            <use xlink:href="#custom-logout-1-outline"></use>
                                        </svg>تسجيل الخروج
                                    </button>

                                    <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
                                        @csrf
                                    </form>


                                </div>




                            </div>
                        </div>
                    </div>
                </li>

            </ul>

        </div>
    </div>
</header>




<!-- Modal -->
<div class="row" dir="rtl">
    <div class="col-12">

        <div class="modal fade returnOrder" tabindex="-1" role="dialog" aria-labelledby="orderdetailsModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="orderdetailsModalLabel">صورة من الاشعار</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                        <div class="row text-center">
                            <div class="col-12 pt-3">
                                <img src="" id="notificationImage" alt="img" class="img-fluid" style="display: none;" />
                                <embed src="" id="notificationPdf" type="application/pdf" width="100%" height="600px" style="display: none;">                            </div>
                        </div>

                        <div dir="rtl">
                            <div class="row mt-2">
                                <div class="col-12 text-end">
                                    <button type="button" class="btn btn-light me-1"
                                        data-bs-dismiss="modal">إغلاق</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
<!-- end modal -->


<div class="row" dir="rtl">
    <div class="col-12">

        <div class="modal fade changePassword" tabindex="-1" role="dialog"
            aria-labelledby="orderdetailsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="orderdetailsModalLabel"> تغييــر كلمــة المــرور</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close"></button>
                    </div>
                    <div class="modal-body">

                        <form action="{{ route('changePassword') }}" method="post" name="event-form"
                            id="myForm">
                            @csrf

                            <div class="row" dir="rtl" style="color: black;">
                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label"> كلمة المرور الحالية</label>
                                        <input  class="form-control"
                                            placeholder="كلمة المرور الحالية" type="password"
                                            name="current_password" required
                                            oninvalid="this.setCustomValidity('الرجاء إدخال كلمة المرور الحالية ')"
                                            />
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label"> كلمة المرور الجديدة</label>
                                        <input  class="form-control"
                                            placeholder="كلمة المرور الجديدة" type="password"
                                            name="new_password" required
                                            oninvalid="this.setCustomValidity('الرجاء إدخال كلمة المرور الجديدة ')"
                                            />
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="mb-3">
                                        <label class="form-label"> اعادة كلمة المرور الجديدة</label>
                                        <input  class="form-control"
                                            placeholder="اعادة كلمة المرور الجديدة" type="password"
                                            name="new_confirm_password" required
                                            oninvalid="this.setCustomValidity('الرجاء اعادة إدخال كلمة المرور الجديدة ')"
                                            />
                                    </div>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-6">
                                    <button type="submit" class="btn btn-primary" id="myButton22">
                                        تغييــر</button>
                                </div>
                                <div class="col-6 text-end">
                                    <button type="button" class="btn btn-light me-1"
                                        data-bs-dismiss="modal">إغــلاق</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>


    </div>
</div>

