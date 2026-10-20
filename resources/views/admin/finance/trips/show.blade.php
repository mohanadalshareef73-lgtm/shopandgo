@extends('layout.master')

@section('title')
    <title>رحلة {{ $trip->trip_code }}</title>
@endsection

@section('style')
    @include('admin.finance._style')
@endsection

@php
    use App\Services\TreasuryService as TS;
    $f = fn ($n) => TS::fmt($n);
    $s = $trip->summary();
    $first = $trip->orders->first();
@endphp

@section('content')
    <section class="pc-container">
        <div class="pc-content">
            @include('admin.finance._header', ['title' => 'رحلة ' . $trip->trip_code])
            @include('layout.validation-messages')

            <div class="row g-3 mb-3">
                <div class="col-6 col-xl-3"><div class="fin-kpi accent"><div class="lbl">ربح الرحلة</div><div class="val">{{ $f($s['profit_dinar']) }} <small>د.ل</small></div><div class="sub">{{ $f($s['profit_dollar']) }} $ · {{ $s['margin'] }}%</div></div></div>
                <div class="col-6 col-xl-3"><div class="fin-kpi"><div class="lbl">مجموع الإيصالات</div><div class="val">{{ $f($s['sales_dinar']) }} <small>د.ل</small></div><div class="sub">= {{ $f($s['sales_dollar']) }} $ بسعر {{ $f($trip->rate, 3) }}</div></div></div>
                <div class="col-6 col-xl-3"><div class="fin-kpi"><div class="lbl">دفعنا للموقع</div><div class="val">{{ $f($trip->cost) }} <small>{{ TS::sign($trip->cost_currency) }}</small></div><div class="sub">= {{ $f($s['cost_dinar']) }} د.ل · من {{ optional($trip->payment)->name }}</div></div></div>
                <div class="col-6 col-xl-3"><div class="fin-kpi"><div class="lbl">الحالة</div><div class="val" style="font-size:20px">{{ $s['status'] ?: '—' }}</div><div class="sub">{{ $s['orders'] }} إيصال · {{ $s['pieces'] }} قطعة</div></div></div>
            </div>

            <div class="row g-3">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-card-title">الإيصالات في الرحلة</div>
                            <div class="table-responsive">
                                <table class="table table-sm fin-table">
                                    <thead><tr><th>الإيصال</th><th>الزبون</th><th class="text-end">القطع</th><th class="text-end">القيمة</th><th class="text-end">المدفوع</th><th class="text-end">الشحن</th><th class="text-end">الباقي</th><th>وصل؟</th><th></th></tr></thead>
                                    <tbody>
                                        @foreach ($trip->orders as $o)
                                            <tr class="{{ $o->status == 'canceld' ? 'text-decoration-line-through text-muted' : '' }}">
                                                <td>{{ $o->code }}</td>
                                                <td>{{ optional($o->customer)->name }}</td>
                                                <td class="fin-num">{{ $o->received_qty !== null ? $o->received_qty . '/' : '' }}{{ $o->qty }}</td>
                                                <td class="fin-num">{{ $f($o->total_price) }} {{ $o->currency_sign }}</td>
                                                <td class="fin-num text-success">{{ $f($o->paid_price) }}</td>
                                                <td class="fin-num">{{ $o->ship_price ? $f($o->ship_price) : '—' }}</td>
                                                <td class="fin-num {{ $o->remaining() > 0 ? 'text-danger' : '' }}">{{ $f($o->remaining()) }}</td>
                                                <td>
                                                    @if ($o->received_at || $o->arrived)
                                                        <span class="badge bg-light-success text-success">وصل{{ $o->storage_num ? ' · رف ' . $o->storage_num : '' }}</span>
                                                    @else
                                                        @can('order-receive')<a href="{{ route('order.receive.form', $o->id) }}" class="btn btn-sm btn-outline-success">استلام</a>@endcan
                                                    @endif
                                                </td>
                                                <td>
                                                    @can('trip-purchase-create')
                                                        <form method="post" action="{{ route('finance.trips.detach', [$trip->id, $o->id]) }}" onsubmit="return confirm('نخرجو الإيصال من الرحلة؟')">
                                                            @csrf @method('DELETE')
                                                            <button class="btn btn-sm btn-link text-danger p-0">إخراج</button>
                                                        </form>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            @can('trip-purchase-create')
                                @if ($freeOrders->count())
                                    <form method="post" action="{{ route('finance.trips.attach', $trip->id) }}" class="d-flex gap-2 mt-2">
                                        @csrf
                                        <select name="order_ids[]" class="form-select" multiple size="4">
                                            @foreach ($freeOrders as $o)
                                                <option value="{{ $o->id }}">{{ $o->code }} — {{ optional($o->customer)->name }} — {{ $f($o->total_price) }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-outline-primary">إضافة للرحلة</button>
                                    </form>
                                @endif
                            @endcan
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    @if ($first)
                        <div class="card">
                            <div class="card-body">
                                <div class="fin-card-title">تغيير حالة الرحلة</div>
                                {{-- نفس آلية "تفاصيل الرحلة" الموجودة: تحدث كل الإيصالات وترسل واتساب --}}
                                <form method="post" action="{{ route('order.trip_details', $first->id) }}">
                                    @csrf
                                    <input type="hidden" name="trip_details" value="{{ $trip->trip_code }}">
                                    <select name="trip_status" class="form-select mb-2">
                                        @foreach ($statuses as $st)
                                            <option value="{{ $st->name }}" @selected($s['status'] == $st->name)>{{ $st->name }}</option>
                                        @endforeach
                                    </select>
                                    <textarea name="trip_note" class="form-control mb-2" rows="2" placeholder="ملاحظة للزبائن (اختياري)">{{ $first->trip_note }}</textarea>
                                    <button class="btn btn-primary w-100">حفظ الحالة وإبلاغ الزبائن</button>
                                </form>
                            </div>
                        </div>
                    @endif

                    @can('trip-purchase-create')
                        <div class="card">
                            <div class="card-body">
                                <div class="fin-card-title">تعديل بيانات الشراء</div>
                                <form method="post" action="{{ route('finance.trips.update', $trip->id) }}">
                                    @csrf @method('PUT')
                                    <div class="row g-2">
                                        <div class="col-6"><label class="form-label">رقم الرحلة</label><input name="trip_code" class="form-control" value="{{ $trip->trip_code }}"></div>
                                        <div class="col-6"><label class="form-label">التاريخ</label><input type="date" name="purchased_at" class="form-control" value="{{ $trip->purchased_at->format('Y-m-d') }}"></div>
                                        <div class="col-6"><label class="form-label">دفعت (دولار)</label><input type="number" step="any" name="cost" class="form-control" value="{{ $trip->cost }}"></div>
                                        <div class="col-6"><label class="form-label">سعر الصرف</label><input type="number" step="any" name="rate" class="form-control" value="{{ $trip->rate }}"></div>
                                        <input type="hidden" name="cost_currency" value="{{ $trip->cost_currency }}">
                                        <div class="col-12"><label class="form-label">دفعت من</label>
                                            <select name="payment_id" class="form-select">
                                                @foreach ($payments as $p)<option value="{{ $p->id }}" @selected($trip->payment_id == $p->id)>{{ $p->name }}</option>@endforeach
                                            </select>
                                        </div>
                                        <div class="col-12"><div class="form-check"><input type="checkbox" class="form-check-input" name="coupon_used" value="1" id="cp" @checked($trip->coupon_used)><label for="cp" class="form-check-label">كوبون</label></div></div>
                                        <div class="col-12"><input name="note" class="form-control" value="{{ $trip->note }}" placeholder="ملاحظة"></div>
                                    </div>
                                    <button class="btn btn-outline-primary w-100 mt-2">حفظ التعديل</button>
                                </form>
                                @can('trip-purchase-delete')
                                    <form method="post" action="{{ route('finance.trips.destroy', $trip->id) }}" class="mt-2" onsubmit="return confirm('نحذفو تسجيل الشراء ونرجعو المبلغ للخزينة؟')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-link text-danger w-100">حذف تسجيل الشراء</button>
                                    </form>
                                @endcan
                                <div class="fin-hint mt-2">سجّلها {{ optional($trip->createdUser)->name }} · {{ $trip->created_at->format('Y-m-d H:i') }}</div>
                            </div>
                        </div>
                    @endcan
                </div>
            </div>
        </div>
    </section>
@endsection
