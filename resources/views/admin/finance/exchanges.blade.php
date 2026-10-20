@extends('layout.master')

@section('title')
    <title>شراء دولار</title>
@endsection

@section('style')
    @include('admin.finance._style')
@endsection

@php
    use App\Services\TreasuryService as TS;
    use App\Models\CurrencyExchange;
    $f = fn ($n) => TS::fmt($n);
    $visa = $payments->first(fn ($p) => preg_match('/visa|فيزا|usdt/i', $p->name));
@endphp

@section('content')
    <section class="pc-container">
        <div class="pc-content">
            @include('admin.finance._header', ['title' => 'شراء دولار / USDT'])
            @include('layout.validation-messages')

            <div class="row g-3">
                @can('exchange-create')
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <div class="fin-card-title">عملية جديدة</div>
                                <form method="post" action="{{ route('finance.exchanges.store') }}" id="exForm">
                                    @csrf
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <label class="form-label">النوع</label>
                                            <select name="kind" class="form-select">
                                                @foreach (CurrencyExchange::KINDS as $k => $v)
                                                    <option value="{{ $k }}" @selected(old('kind') == $k)>{{ $v }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label">التاريخ</label>
                                            <input type="date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}" required>
                                        </div>
                                    </div>

                                    <div class="fin-result mt-3">
                                        <div class="fw-bold mb-2"><span class="fin-step">1</span>دفعت من</div>
                                        <div class="row g-2">
                                            <div class="col-5">
                                                <select name="from_payment_id" class="form-select" required>
                                                    @foreach ($payments as $p)
                                                        <option value="{{ $p->id }}" @selected(old('from_payment_id', 1) == $p->id)>{{ $p->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-3">
                                                <select name="from_currency" class="form-select">
                                                    @foreach (TS::CURRENCY_NAMES as $c => $n)
                                                        <option value="{{ $c }}" @selected(old('from_currency', 'dinar') == $c)>{{ $n }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <input type="number" step="any" min="0" name="from_amount" id="fromAmount" class="form-control fin-big-input" placeholder="0" value="{{ old('from_amount') }}" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="fin-result mt-2">
                                        <div class="fw-bold mb-2"><span class="fin-step">2</span>استلمت في</div>
                                        <div class="row g-2">
                                            <div class="col-5">
                                                <select name="to_payment_id" class="form-select" required>
                                                    @foreach ($payments as $p)
                                                        <option value="{{ $p->id }}" @selected(old('to_payment_id', optional($visa)->id) == $p->id)>{{ $p->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-3">
                                                <select name="to_currency" class="form-select">
                                                    @foreach (TS::CURRENCY_NAMES as $c => $n)
                                                        <option value="{{ $c }}" @selected(old('to_currency', 'dollar') == $c)>{{ $n }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <input type="number" step="any" min="0" name="to_amount" id="toAmount" class="form-control fin-big-input" placeholder="0" value="{{ old('to_amount') }}" required>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <div>سعر الصرف: <b id="rateOut" class="fs-5">—</b></div>
                                        @if ($latestRate)<small class="text-muted">آخر سعر: {{ $f($latestRate) }}</small>@endif
                                    </div>

                                    <div class="mt-2">
                                        <input type="text" name="note" class="form-control" placeholder="ملاحظة (اختياري) مثلا: من محل فلان" value="{{ old('note') }}">
                                    </div>

                                    @if (!$visa)
                                        <div class="fin-hint mt-2">💡 نصيحة: أضف طريقة دفع اسمها "فيزا" من الإعدادات ← طرق الدفع، باش يكون عندك خزينة خاصة بالفيزا.</div>
                                    @endif

                                    <button class="btn btn-primary w-100 mt-3 btn-lg">حفظ العملية</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endcan

                <div class="col-lg-7">
                    <div class="row g-3 mb-3">
                        <div class="col-4"><div class="fin-kpi"><div class="lbl">دينار مدفوع هالشهر</div><div class="val" style="font-size:20px">{{ $f($monthStats->lyd) }}</div></div></div>
                        <div class="col-4"><div class="fin-kpi"><div class="lbl">دولار مستلم هالشهر</div><div class="val" style="font-size:20px">{{ $f($monthStats->usd) }}</div></div></div>
                        <div class="col-4"><div class="fin-kpi"><div class="lbl">متوسط السعر</div><div class="val" style="font-size:20px">{{ $monthStats->usd > 0 ? $f($monthStats->lyd / $monthStats->usd, 3) : '—' }}</div></div></div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="fin-card-title">الأرصدة الحالية</div>
                            <div class="d-flex flex-wrap gap-2">
                                @foreach ($payments as $p)
                                    @foreach ($balances[$p->id] as $c => $v)
                                        @if (abs($v) > 0.001)
                                            <span class="badge bg-light-secondary text-dark p-2" style="font-size:13px">{{ $p->name }}: <b dir="ltr">{{ $f($v) }} {{ TS::sign($c) }}</b></span>
                                        @endif
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="fin-card-title">السجل</div>
                            <div class="table-responsive">
                                <table class="table table-sm fin-table">
                                    <thead><tr><th>التاريخ</th><th>النوع</th><th>دفعنا</th><th>استلمنا</th><th>السعر</th><th>بواسطة</th><th></th></tr></thead>
                                    <tbody>
                                        @forelse ($exchanges as $ex)
                                            <tr>
                                                <td>{{ $ex->date->format('Y-m-d') }}</td>
                                                <td>{{ CurrencyExchange::KINDS[$ex->kind] ?? $ex->kind }}@if ($ex->note)<br><small class="text-muted">{{ $ex->note }}</small>@endif</td>
                                                <td class="fin-num">{{ $f($ex->from_amount) }} {{ TS::sign($ex->from_currency) }}<br><small class="text-muted">{{ optional($ex->fromPayment)->name }}</small></td>
                                                <td class="fin-num">{{ $f($ex->to_amount) }} {{ TS::sign($ex->to_currency) }}<br><small class="text-muted">{{ optional($ex->toPayment)->name }}</small></td>
                                                <td class="fin-num">{{ $f($ex->rate, 4) }}</td>
                                                <td>{{ optional($ex->createdUser)->name }}</td>
                                                <td>
                                                    @can('exchange-delete')
                                                        <form method="post" action="{{ route('finance.exchanges.destroy', $ex->id) }}" onsubmit="return confirm('متأكد؟ الأرصدة بترجع زي ما كانت')">
                                                            @csrf @method('DELETE')
                                                            <button class="btn btn-sm btn-link text-danger p-0">حذف</button>
                                                        </form>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="7" class="text-muted text-center py-4">ما فيش عمليات مسجلة</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $exchanges->links() }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script>
        (function () {
            var a = document.getElementById('fromAmount'), b = document.getElementById('toAmount'), out = document.getElementById('rateOut');
            if (!a) return;
            function calc() {
                var x = parseFloat(a.value), y = parseFloat(b.value);
                out.textContent = (x > 0 && y > 0) ? (x / y).toFixed(4).replace(/\.?0+$/, '') : '—';
            }
            a.addEventListener('input', calc); b.addEventListener('input', calc); calc();
        })();
    </script>
@endsection
