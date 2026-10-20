@extends('layout.master')

@section('title')
    <title> تعديل تعديل المشتريات </title>
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
                                <h2 class="mb-0">تعديل مصروفات الدفع <span class="text-primary">{{ $expense->from }} - {{ $expense->to }} - {{ $expense->platform->name }}</span> </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->

            @include('layout.validation-messages')


            <form action="{{ route('paymentExpenses.update', $expense->id) }}" id="orders_form" method="post" enctype="multipart/form-data">
                @method('PUT')
                @csrf
                <input type="hidden" name="checked_order_ids" id="checked_order_ids">


                <!-- [ Main Content ] start -->
                <div class="row">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5>تعديل البيانات</h5>
                            </div>
                            <div class="card-body">

                                <div class="row">

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">من كود الرحلة</label>
                                            <input type="number" value="{{ $expense->from }}" name="from" class="form-control"
                                                id="exampleInputName343" required placeholder="من كود الرحلة"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال من كود الرحلة')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">الي كود الرحلة</label>
                                            <input type="number" name="to" value="{{ $expense->to }}" class="form-control"
                                                id="exampleInputName343" required placeholder="الي كود الرحلة"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال الي كود الرحلة')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6" id="desc_select_container">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تحديد المنصة</label>
                                            <select name="platform_id" id="desc_select" class="form-control">
                                                <option>-- اختر المنصة --</option>
                                                @foreach ($platforms as $platform)
                                                @if ($platform->id == $expense->platform_id)
                                                <option selected value="{{ $platform->id }}">{{ $platform->name }}</option>
                                                @else
                                                <option value="{{ $platform->id }}">{{ $platform->name }}</option>
                                                @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-6" style="display: none;" id="new_desc">
                                        <div class="form-group">
                                            <label class="form-label" for="new_desc_name">منصة جديدة</label>
                                            <input type="text" name="name" class="form-control" id="new_desc_name" placeholder="منصة جديدة">
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="newDescription" onchange="toggleDescription()">
                                                <label class="form-check-label" for="newDescription">منصة جديدة</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">قيمة المصروفات</label>
                                            <input type="number" step="any" disabled value="{{ $expense->cost }}" name="cost" class="form-control"
                                                id="exampleInputName343" required placeholder="قيمة المصروفات"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال قيمة المصروفات')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تاريخ  مصروفات الدفع</label>
                                            <input type="date" name="date" class="form-control" value="{{ $expense->date }}"
                                                id="exampleInputName343" required placeholder="تاريخ  مصروفات الدفع"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ  مصروفات الدفع')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">ملاحطات (إختياري)</label>
                                            <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                placeholder="ملاحظات (إختياري)">{{ $expense->note }}</textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <div class="form-check">
                                                @if ($expense->status)
                                                <input class="form-check-input" checked type="checkbox" name="status"
                                                id="invalidCheck">
                                                @else
                                                <input class="form-check-input" type="checkbox" name="status"
                                                    id="invalidCheck">
                                                @endif

                                                <label class="form-check-label" for="invalidCheck">مـدفـوعـة</label>
                                            </div>
                                        </div>
                                        <button type="submit" class="btn btn-primary mb-4">حفظ التعديلات</button>
                                    </div>

                                </div>

                            </div>

                        </div>
                    </div>

                </div>
                <!-- [ form-element ] end -->


            </form>


        </div>
        </div>
    </section>
@endsection

@section('script')
    <script>
                function toggleDescription() {
            const newDescriptionCheckbox = document.getElementById('newDescription');
            const newDescDiv = document.getElementById('new_desc');
            const newDescInpute = document.getElementById('new_desc_name');
            const descSelectContainer = document.getElementById('desc_select_container');
            const descSelect = document.getElementById('desc_select');
            if (newDescriptionCheckbox.checked) {
                newDescDiv.style.display = 'block';
                descSelectContainer.style.display = 'none';
                descSelect.selectedIndex = 0;
            } else {
                newDescDiv.style.display = 'none';
                descSelectContainer.style.display = 'block';
                newDescInpute.value = "";
            }
        }

    </script>
@endsection
