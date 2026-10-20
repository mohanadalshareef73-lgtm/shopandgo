@extends('layout.master')

@section('title')
    <title>الشركاء</title>
@endsection

@section('style')
    @include('admin.finance._style')
@endsection

@php
    use App\Services\TreasuryService as TS;
    use App\Models\PartnerTransaction as PT;
    $f = fn ($n) => TS::fmt($n);
    $sum = [];
    foreach ($totals as $t) { $sum[$t->partner_id][$t->type][$t->currency] = (float) $t->total; }
    $today = [];
    foreach ($todayTotals as $t) { $today[$t->partner_id][$t->currency] = (float) $t->total; }
    $activePartners = $partners->where('status', true);
@endphp

@section('content')
    <section class="pc-container">
        <div class="pc-content">
            @include('admin.finance._header', ['title' => 'الشركاء'])
            @include('layout.validation-messages')

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="fin-kpi accent">
                        <div class="lbl">الفائض اللي ما تقسمش</div>
                        <div class="val">{{ $f($surplus['dinar']['surplus']) }} <small>د.ل</small></div>
                        <div class="sub">ربح صافي {{ $f($surplus['dinar']['net']) }} − مسحوبات {{ $f($surplus['dinar']['drawn']) }}</div>
                    </div>
                </div>
                @foreach ($partners as $p)
                    <div class="col-md-4">
                        <div class="fin-kpi">
                            <div class="lbl">{{ $p->name }} @if (!$p->status)<span class="badge bg-light-secondary text-muted">موقوف</span>@endif</div>
                            <div class="val">{{ $f(($sum[$p->id]['withdrawal']['dinar'] ?? 0) + ($sum[$p->id]['distribution']['dinar'] ?? 0)) }} <small>د.ل مسحوب إجمالي</small></div>
                            <div class="sub">
                                سحب يومي {{ $f($sum[$p->id]['withdrawal']['dinar'] ?? 0) }} ·
                                توزيع {{ $f($sum[$p->id]['distribution']['dinar'] ?? 0) }} ·
                                اليوم {{ $f($today[$p->id]['dinar'] ?? 0) }}
                                @if (!empty($sum[$p->id]['deposit']['dinar'])) · أودع {{ $f($sum[$p->id]['deposit']['dinar']) }} @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="row g-3">
                @can('partner-create')
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-body">
                                <div class="fin-card-title">سحب / توزيع</div>
                                @if ($activePartners->isEmpty())
                                    <div class="fin-hint">أضف الشركاء أول (تحت).</div>
                                @else
                                    <form method="post" action="{{ route('finance.partners.transactions.store') }}">
                                        @csrf
                                        <div class="btn-group w-100 mb-3" role="group">
                                            @foreach (PT::TYPES as $k => $v)
                                                <input type="radio" class="btn-check" name="type" id="t_{{ $k }}" value="{{ $k }}" @checked(old('type', 'withdrawal') == $k)>
                                                <label class="btn btn-outline-primary" for="t_{{ $k }}">{{ $v }}</label>
                                            @endforeach
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-5">
                                                <label class="form-label">من خزينة</label>
                                                <select name="payment_id" class="form-select">
                                                    @foreach ($payments as $p)
                                                        <option value="{{ $p->id }}" @selected(old('payment_id', 1) == $p->id)>{{ $p->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-3">
                                                <label class="form-label">العملة</label>
                                                <select name="currency" class="form-select">
                                                    @foreach (TS::CURRENCY_NAMES as $c => $n)
                                                        <option value="{{ $c }}" @selected(old('currency', 'dinar') == $c)>{{ $n }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label">التاريخ</label>
                                                <input type="date" name="date" class="form-control" value="{{ old('date', now()->toDateString()) }}">
                                            </div>
                                        </div>

                                        <div class="fin-result mt-3">
                                            @foreach ($activePartners as $p)
                                                <div class="d-flex align-items-center gap-2 mb-2">
                                                    <div style="min-width:110px" class="fw-bold">{{ $p->name }}</div>
                                                    <input type="number" step="any" min="0" name="amounts[{{ $p->id }}]" class="form-control fin-big-input partner-amount" placeholder="0" value="{{ old('amounts.' . $p->id) }}">
                                                </div>
                                            @endforeach
                                            @if ($surplus['dinar']['surplus'] > 0)
                                                <button type="button" class="btn btn-sm btn-link px-0" id="splitEqual" data-surplus="{{ round($surplus['dinar']['surplus'], 2) }}" data-n="{{ $activePartners->count() }}">
                                                    قسّم الفائض ({{ $f($surplus['dinar']['surplus']) }}) بالتساوي
                                                </button>
                                            @endif
                                        </div>

                                        <input type="text" name="note" class="form-control mt-2" placeholder="ملاحظة (اختياري)" value="{{ old('note') }}">
                                        <button class="btn btn-primary w-100 mt-3 btn-lg">حفظ</button>
                                        <div class="fin-hint mt-2">السحب اليومي والتوزيع ينقصو من الخزينة ومن الفائض. الإيداع (لو الشريك حط فلوس من جيبه) يزيد في الخزينة.</div>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-body">
                                <div class="fin-card-title">الشركاء</div>
                                @foreach ($partners as $p)
                                    <form method="post" action="{{ route('finance.partners.update', $p->id) }}" class="d-flex gap-2 mb-2 align-items-center">
                                        @csrf @method('PUT')
                                        <input name="name" class="form-control" value="{{ $p->name }}">
                                        <input name="phone" class="form-control" value="{{ $p->phone }}" placeholder="الهاتف">
                                        <input type="hidden" name="status" value="0">
                                        <div class="form-check form-switch m-0" title="فعال"><input class="form-check-input" type="checkbox" name="status" value="1" @checked($p->status)></div>
                                        <button class="btn btn-sm btn-outline-secondary">حفظ</button>
                                    </form>
                                @endforeach
                                <form method="post" action="{{ route('finance.partners.store') }}" class="d-flex gap-2 mt-3">
                                    @csrf
                                    <input name="name" class="form-control" placeholder="اسم شريك جديد" required>
                                    <input name="phone" class="form-control" placeholder="الهاتف">
                                    <button class="btn btn-primary">إضافة</button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endcan

                <div class="col-lg-7">
                    <div class="card">
                        <div class="card-body">
                            <div class="fin-card-title">سجل الحركات</div>
                            <form method="get" class="row g-2 mb-3">
                                <div class="col-6 col-md-3">
                                    <select name="partner_id" class="form-select"><option value="">كل الشركاء</option>
                                        @foreach ($partners as $p)<option value="{{ $p->id }}" @selected(request('partner_id') == $p->id)>{{ $p->name }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-6 col-md-3">
                                    <select name="type" class="form-select"><option value="">كل الأنواع</option>
                                        @foreach (PT::TYPES as $k => $v)<option value="{{ $k }}" @selected(request('type') == $k)>{{ $v }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-5 col-md-2"><input type="date" name="from" class="form-control" value="{{ request('from') }}"></div>
                                <div class="col-5 col-md-2"><input type="date" name="to" class="form-control" value="{{ request('to') }}"></div>
                                <div class="col-2 col-md-2"><button class="btn btn-dark w-100">بحث</button></div>
                            </form>
                            <div class="table-responsive">
                                <table class="table table-sm fin-table">
                                    <thead><tr><th>التاريخ</th><th>الشريك</th><th>النوع</th><th class="text-end">المبلغ</th><th>الخزينة</th><th></th></tr></thead>
                                    <tbody>
                                        @forelse ($transactions as $tx)
                                            <tr>
                                                <td>{{ $tx->date->format('Y-m-d') }}</td>
                                                <td>{{ $tx->partner->name }}</td>
                                                <td>
                                                    <span class="badge {{ $tx->type === 'deposit' ? 'bg-light-success text-success' : ($tx->type === 'distribution' ? 'bg-light-primary text-primary' : 'bg-light-warning text-warning') }}">{{ PT::TYPES[$tx->type] }}</span>
                                                    @if ($tx->note)<br><small class="text-muted">{{ $tx->note }}</small>@endif
                                                </td>
                                                <td class="fin-num {{ $tx->isOutflow() ? 'text-danger' : 'text-success' }}">{{ $tx->isOutflow() ? '−' : '+' }}{{ $f($tx->amount) }} {{ TS::sign($tx->currency) }}</td>
                                                <td>{{ optional($tx->payment)->name }}</td>
                                                <td>
                                                    @can('partner-delete')
                                                        <form method="post" action="{{ route('finance.partners.transactions.destroy', $tx->id) }}" onsubmit="return confirm('متأكد من الحذف؟')">
                                                            @csrf @method('DELETE')
                                                            <button class="btn btn-sm btn-link text-danger p-0">حذف</button>
                                                        </form>
                                                    @endcan
                                                </td>
                                            </tr>
                                        @empty
                                            <tr><td colspan="6" class="text-center text-muted py-4">ما فيش حركات</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            {{ $transactions->links() }}
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
            var btn = document.getElementById('splitEqual');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var s = parseFloat(btn.dataset.surplus), n = parseInt(btn.dataset.n, 10);
                var share = Math.floor((s / n) * 100) / 100;
                document.querySelectorAll('.partner-amount').forEach(function (i) { i.value = share; });
                var d = document.getElementById('t_distribution'); if (d) d.checked = true;
            });
        })();
    </script>
@endsection
