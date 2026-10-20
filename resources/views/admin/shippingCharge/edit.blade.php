@extends('layout.master')

@section('title')
    <title> تعديل تعديل مصروفات الشحن </title>
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
                                <li class="breadcrumb-item"><a href="{{ route('shippingCharges.index') }}">مصروفات الشحن</a></li>
                                <li class="breadcrumb-item" aria-current="page">تعديل البيانات</li>
                            </ul>
                        </div>
                        <div class="col-md-12">
                            <div class="page-header-title">
                                <h2 class="mb-0">تعديل بيانات <span class="text-primary">{{ $charge->type->name }}</span> </h2>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- [ breadcrumb ] end -->

            @include('layout.validation-messages')


            <form action="{{ route('shippingCharges.update', $charge->id) }}" id="orders_form" method="post" enctype="multipart/form-data">
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

                                    <div class="col-md-4" id="type_select_container">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تحديد الوصف</label>
                                            <select name="type_id" id="type_select" class="form-control">
                                                <option>-- اختر الوصـف --</option>
                                                @foreach ($types as $type)
                                                @if ($type->id == $charge->type_id)
                                                <option selected value="{{ $type->id }}">{{ $type->name }}</option>
                                                @else
                                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                                                @endif
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-4" style="display: none;" id="new_type">
                                        <div class="form-group">
                                            <label class="form-label" for="new_desc_name">نوع شحن جديد</label>
                                            <input type="text" name="name" class="form-control" id="new_desc_name" placeholder="نوع شحن جديد">
                                        </div>
                                    </div>


                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">قيمة مصروفات الشحن</label>
                                            <input type="number" step="any" disabled value="{{ $charge->cost }}" name="cost" class="form-control"
                                                id="exampleInputName343" required placeholder="قيمة مصروفات الشحن"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال قيمة مصروفات الشحن')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>


                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="currency">العملة </label>
                                                <select name="currency" disabled required id="currency" class="form-control">
                                                    <option value="">-- اختر العملة --</option>
                                                    <option {{ $charge->currency == 'dinar' ? 'selected' : '' }} value="dinar">دينار ليبي - د.ل</option>
                                                    <option {{ $charge->currency == 'dollar' ? 'selected' : '' }} value="dollar">دولار - $</option>
                                                    <option {{ $charge->currency == 'euro' ? 'selected' : '' }} value="euro">يورو - €</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-group">
                                                <label class="form-label" for="payment_id">طريقة الدفع </label>
                                                <select name="payment_id" disabled required id="payment_id" class="form-control">
                                                    <option>-- اختر طريقة الدفع --</option>
                                                    @foreach ($payments as $index => $payment)
                                                    @if ($payment->id == $charge->payment_id)
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
                                                <label class="form-check-label" for="newDescription">نوع شحن جديد</label>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">وزن الشحنة</label>
                                            <input type="number" step="any" name="weight"
                                                class="form-control" id="exampleInputName343"
                                                placeholder="وزن الشحنة" value="{{ $charge->weight }}" required
                                                oninvalid="this.setCustomValidity('الرجاء ادخال وزن الشحنة')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>

{{-- عدد الطرود + شركة الشحن + الرحلة (للمقارنة مع دخل الشحن من الزبائن) --}}
<div class="col-md-4">
    <div class="form-group">
        <label class="form-label">عدد الطرود</label>
        <input type="number" min="0" name="parcels_count" class="form-control" placeholder="عدد الطرود" value="{{ $charge->parcels_count }}">
    </div>
</div>
<div class="col-md-4">
    <div class="form-group">
        <label class="form-label">شركة الشحن</label>
        <input type="text" name="company" class="form-control" placeholder="اسم شركة الشحن" value="{{ $charge->company }}">
    </div>
</div>
<div class="col-md-4">
    <div class="form-group">
        <label class="form-label">الرحلة (اختياري)</label>
        <select name="trip_id" class="form-control">
            <option value="">-- بدون --</option>
            @foreach (\App\Models\Trip::latest()->take(40)->get() as $tripOpt)
                <option value="{{ $tripOpt->id }}" @selected($charge->trip_id == $tripOpt->id)>{{ $tripOpt->code }} - {{ $tripOpt->country }}</option>
            @endforeach
        </select>
    </div>
</div>


                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName343">تاريخ  المـشـتـريـات</label>
                                            <input type="date" name="date" class="form-control" value="{{ $charge->date }}"
                                                id="exampleInputName343" required placeholder="تاريخ  المـشـتـريـات"
                                                oninvalid="this.setCustomValidity('الرجاء ادخال تاريخ  المـشـتـريـات')"
                                                oninput="this.setCustomValidity('')">
                                        </div>
                                    </div>



                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label class="form-label" for="exampleInputName">ملاحطات (إختياري)</label>
                                            <textarea type="text" name="note" class="form-control" id="exampleInputName" aria-typeribedby="nameHelp"
                                                placeholder="ملاحظات (إختياري)">{{ $charge->note }}</textarea>
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
            const newDescDiv = document.getElementById('new_type');
            const newDescInpute = document.getElementById('new_desc_name');
            const descSelectContainer = document.getElementById('type_select_container');
            const descSelect = document.getElementById('type_select');
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
