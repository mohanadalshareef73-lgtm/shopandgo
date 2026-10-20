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
                                <li class="breadcrumb-item"><a href="{{ route('purchase.index') }}">المشتريات</a></li>
                                <li class="breadcrumb-item" aria-current="page">تعديل البيانات</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">تعديل بيانات <span class="text-primary">{{ $purchase->desc->name }}</span> </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->

            @include('layout.validation-messages')


            <form action="{{ route('purchase.update', $purchase->id) }}" id="orders_form" method="post" enctype="multipart/form-data">
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

                                    <div class="col-md-4" id="desc_select_container">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تحديد الوصف</label>
                                            <select name="desc_id" id="desc_select" class="form-control">
                                                <option>-- اختر الوصـف --</option>
                                                @foreach ($descriptions as $description)
                                                @if ($description->id == $purchase->desc_id)
                                                <option selected value="{{ $description->id }}">{{ $description->name }}</option>
                                                @else
                                                <option value="{{ $description->id }}">{{ $description->name }}</option>
                                                @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-4" style="display: none;" id="new_desc">
                                        <div class="form-group">
                                            <label class="form-label" for="new_desc_name">وصـف جـديـد</label>
                                            <input type="text" name="name" class="form-control" id="new_desc_name" placeholder="وصـف جـديـد">
                                        </div>
                                    </div>


                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">قيمة المشتريات</label>
                                            <input type="number" step="any" disabled value="{{ $purchase->cost }}" name="cost" class="form-control"
                                                id="exampleInputName343" required placeholder="قيمة المشتريات"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال قيمة المشتريات')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>


                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="currency">العملة </label>
                                                <select name="currency" required id="currency". disabled class="form-control">
                                                    <option value="">-- اختر العملة --</option>
                                                    <option {{ $purchase->currency == 'dinar' ? 'selected' : '' }} value="dinar">دينار ليبي - د.ل</option>
                                                    <option {{ $purchase->currency == 'dollar' ? 'selected' : '' }} value="dollar">دولار - $</option>
                                                    <option {{ $purchase->currency == 'euro' ? 'selected' : '' }} value="euro">يورو - €</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="payment_id">طريقة الدفع </label>
                                                <select name="payment_id" disabled required id="payment_id" class="form-control">
                                                    <option>-- اختر طريقة الدفع --</option>
                                                    @foreach ($payments as $index => $payment)
                                                    @if ($payment->id == $purchase->payment_id)
                                                        <option value="{{ $payment->id }}" selected>{{ $payment->name }}</option>
                                                    @else
                                                        <option value="{{ $payment->id }}">{{ $payment->name }}</option>
                                                    @endif
                                                    @endforeach

                                                </select>
                                            </div>
                                        </div>

                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="newDescription" onchange="toggleDescription()">
                                                <label class="form-check-label" for="newDescription">وصـف جـديـد</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تاريخ  المـشـتـريـات</label>
                                            <input type="date" name="date" class="form-control" value="{{ $purchase->date }}"
                                                id="exampleInputName343" required placeholder="تاريخ  المـشـتـريـات"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ  المـشـتـريـات')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">ملاحطات (إختياري)</label>
                                            <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-describedby="nameHelp"
                                                placeholder="ملاحظات (إختياري)">{{ $purchase->note }}</textarea>
                                        </div>
                                    </div>

                                    <div class="col-md-12">
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
