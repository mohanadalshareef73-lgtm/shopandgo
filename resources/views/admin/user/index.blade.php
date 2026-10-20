@extends('layout.master')

@section('title')
    <title> المستخدمين </title>
@endsection

@section('style')
    <!-- data tables css -->
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/dataTables.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/select.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/autoFill.bootstrap5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/plugins/keyTable.bootstrap5.min.css') }}">
    <!-- [Page specific CSS] end -->
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
                                <li class="breadcrumb-item"><a href="javascript: void(0)">المستخدمين</a></li>
                                <li class="breadcrumb-item" aria-current="page">القائمة</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">المستخدمين</h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->

            @include('layout.validation-messages')

            <!-- [ Main Content ] start -->
            <div class="row">
                <!-- Autofill table start -->
                <div class="col-sm-12">
                    <div class="card">
                        {{-- <div class="card-header">
                        <a href="{{ route('ad.create') }}" class=" btn btn-outline-success">{{ __('messages.ad.add') }}</a>
                    </div> --}}

                    @can('user-create')
                        <div class="card-header">
                            <a class="btn btn-primary m-t-5" data-bs-toggle="collapse" href="#collapseExample"
                                role="button" aria-expanded="false" aria-controls="collapseExample">اضافة مستخدم جديد</a>
                        </div>
                    @endcan

                        <div class="collapse" id="collapseExample">
                            <div class="card-body">
                                <form action="{{ route('users.store') }}" method="post" enctype="multipart/form-data">
                                    @csrf
                                    <div class="row">

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">اسم المستخدم</label>
                                                <input type="text"name="name" class="form-control" id="exampleInputName"
                                                    required aria-describedby="nameHelp" placeholder="اسم المستخدم"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال اسم المستخدم')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleInputName">البريد الالكتروني</label>
                                                <input type="email" name="email" class="form-control"
                                                    id="exampleInputName" required aria-describedby="nameHelp"
                                                    placeholder="البريد الالكتروني"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال البريد الالكتروني')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>


                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="password">كلمة المرور</label>
                                                <input type="password" autocomplete="false" name="password" class="form-control" id="password"
                                                    required aria-describedby="nameHelp" placeholder="كلمة المرور"
                                                    oninvalid="this.setCustomValidity('الرجاء ادخال كلمة المرور')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="password-confirm">إعادة كلمة المرور</label>
                                                <input type="password" name="password_confirmation" class="form-control"
                                                    id="password-confirm" autocomplete="false" required aria-describedby="nameHelp"
                                                    placeholder="كلمة المرور"
                                                    oninvalid="this.setCustomValidity('الرجاء إعادة ادخال كلمة المرور')"
                                                    oninput="this.setCustomValidity('')">
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label class="form-label" for="exampleFormControlSelect1">الـصـلاحـيـة</label>
                                                <select class="form-select" name="roles" required
                                                    oninvalid="this.setCustomValidity('الرجاء تحديد صلاحية المستخدم')"
                                                    oninput="this.setCustomValidity('')" id="exampleFormControlSelect1">
                                                    <option selected disabled>اخــتــيـار</option>
                                                    @foreach ($roles as $role)
                                                    <option value="{{$role->id}}">{{$role->name}}</option>
                                                @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-md-12">
                                            <div class="form-group">
                                                <div class="form-check">
                                                    <input class="form-check-input" checked type="checkbox"
                                                        name="status" id="invalidCheck">
                                                    <label class="form-check-label" for="invalidCheck">تفعيل
                                                        الحساب</label>
                                                </div>
                                            </div>
                                            <button type="submit" class="btn btn-primary mb-4">حفظ المستخدم</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="dt-responsive table-responsive">
                                <div id="autofill_wrapper" class="dataTables_wrapper dt-bootstrap5">
                                    <div class="row dt-row">
                                        <div class="col-sm-12">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Name: activate to sort column ascending">
                                                            اسم المستخدم
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Product: activate to sort column ascending">
                                                            البريد الالكتروني
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 175.717px;"
                                                            aria-label="Status: activate to sort column ascending">
                                                            الحالة
                                                        </th>
                                                        <th class="sorting" tabindex="0" aria-controls="autofill"
                                                            rowspan="1" colspan="1" style="width: 285.883px;"
                                                            aria-label="Position: activate to sort column ascending">
                                                            الاجراءات
                                                        </th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @foreach ($users as $user)
                                                        <tr class="odd">

                                                            <td class="">{{ $user->name }}</td>
                                                            <td class="">{{ $user->email }}</td>

                                                            @can('user-edit')
                                                            <td class="">
                                                                <div class="form-check form-switch custom-switch-v1 mb-2">
                                                                    @if ($user->status)
                                                                        <input type="checkbox"
                                                                            onclick="redirectToAd('{{ route('users.show', $user->id) }}')"
                                                                            class="form-check-input input-primary"
                                                                            id="{{ $user->id }}" checked>
                                                                    @else
                                                                        <input type="checkbox"
                                                                            onclick="redirectToAd('{{ route('users.show', $user->id) }}')"
                                                                            class="form-check-input input-primary"
                                                                            class="form-check-input input-primary"
                                                                            id="{{ $user->id }}">
                                                                    @endif
                                                                    {{-- <label class="form-check-label" for="customswitchv2-1">الـحـالـة</label> --}}
                                                                </div>
                                                            </td>
                                                            @else
                                                            <td class=""> - </td>
                                                            @endcan

                                                            @can('user-edit')
                                                            <td>
                                                                <a href="{{ route('users.edit', $user->id) }}"
                                                                    class="btn btn-light-primary">تعديل
                                                                </a>
                                                            </td>
                                                            @else
                                                                <td class=""> - </td>
                                                            @endcan
                                                        </tr>
                                                    @endforeach

                                                </tbody>

                                                <tfoot>

                                                    <tr>
                                                        <th rowspan="1" colspan="1">اسم المستخدم</th>
                                                        <th rowspan="1" colspan="1">البريد الالكتروني
                                                        </th>
                                                        <th rowspan="1" colspan="1">نوع المستخدم</th>
                                                        <th rowspan="1" colspan="1">الاجراءات
                                                        </th>
                                                    </tr>

                                                </tfoot>
                                            </table>
                                                @if(isset($query))
                                                <span id="paginate">{{ $users->appends($query)->links('pagination::bootstrap-4') }}</span>
                                            @else
                                                <span id="paginate">{{ $users->links('pagination::bootstrap-4') }}</span>
                                            @endif

                                        </div>
                                    </div>

                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <!-- Autofill table end -->

            </div>
            <!-- [ Main Content ] end -->
        </div>
    </section>

    <script>
        function redirectToAd(route) {
            // Redirect to the specified route
            window.location.href = route;
        }
    </script>
@endsection
@section('script')
    <!-- datatable Js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    {{-- <script src="../assets/js/plugins/apexcharts.min.js"></script>
 <script src="../assets/js/pages/dashboard-default.js"></script> --}}

    <script src="{{ asset('assets/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.autoFill.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/autoFill.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.keyTable.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/keyTable.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.select.min.js') }}"></script>

    <script>
        // [ Autofill ]
        $('#autofill').DataTable({
            autoFill: true
        });
        // [ KeyTable Integration ]
        $('#key-integration').DataTable({
            keys: true,
            autoFill: true
        });
        // [ Column Selector ]
        $('#confirm-table').DataTable({
            autoFill: {
                alwaysAsk: true
            }
        });

        // [ scroll fill ]
        var safill = $('#scroll-fill').dataTable({
            scrollY: 400,
            scrollCollapse: true,
            paging: false,
            autoFill: true
        });
        $('#colum-select').DataTable({
            columnDefs: [{
                orderable: false,
                className: 'select-checkbox',
                targets: 0
            }],
            select: {
                style: 'os',
                selector: 'td:first-child'
            },
            order: [
                [1, 'asc']
            ],
            autoFill: {
                columns: ':not(:first-child)'
            }
        });
    </script>
@endsection
