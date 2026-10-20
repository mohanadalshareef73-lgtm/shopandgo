@extends('layout.master')

@section('title')
    <title>
        طلبات المنصة </title>
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
                                <li class="breadcrumb-item" aria-current="page">القائمة</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0"> طلبات المنصة <span class="text-primary">{{ $platform->code }} -
                                        {{ $platform->name }}</span> </h2>
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
                        @if ($orders->count() == 0)
                            <div class="card-header">
                                <h5>لايوجد اي طلبات لهذا المنصة</h5>
                            </div>
                        @endif


                                                @include('admin.order.components.order')


                    </div>
                </div>
                <!-- Autofill table end -->
            </div>
            <!-- [ Main Content ] end -->
        </div>
    </section>
@endsection

@section('script')
    <!-- datatable Js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="{{ asset('assets/js/plugins/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.autoFill.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/autoFill.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.keyTable.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/keyTable.bootstrap5.min.js') }}"></script>
    <script src="{{ asset('assets/js/plugins/dataTables.select.min.js') }}"></script>


    <script>
        function toggleCheckbox(event, id) {
            event.stopPropagation();
            const checkbox = document.getElementById(id);
            checkbox.checked = !checkbox.checked;
            togglePriceInput(id, checkbox.checked);
        }

        function checkRow(event, id) {
            if (event.target.type === 'checkbox' || event.target.type === 'number') {
                return;
            }
            const checkbox = document.getElementById(id);
            checkbox.checked = !checkbox.checked;
            togglePriceInput(id, checkbox.checked);
        }

        function togglePriceInput(id, isChecked) {
            const orderId = id.replace('checkboxOrders', '');
            const priceInput = document.querySelector(`input[name="price[${orderId}]"]`);
            if (isChecked) {
                priceInput.removeAttribute('disabled');
            } else {
                priceInput.setAttribute('disabled', 'disabled');
            }
        }

        function submitCheckedOrders() {
            const checkedBoxes = document.querySelectorAll('input[name="checkedBox"]:checked');
            const ids = Array.from(checkedBoxes).map(cb => cb.value);
            document.getElementById('checked_order_ids').value = ids.join(',');
            console.log(document.getElementById('checked_order_ids').value); // Debugging line
            document.getElementById('orders_form').submit();
        }
    </script>
@endsection
