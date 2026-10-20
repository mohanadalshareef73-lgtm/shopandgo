@push('style')
<style>
.wallets { display: grid; grid-template-columns: repeat(auto-fill, minmax(210px, 1fr)); gap: 12px; margin-bottom: 14px; }
.wallet { border-radius: 16px; padding: 16px; color: #fff; background: var(--brand); position: relative; }
.wallet.c1 { background: #2a78d6; } .wallet.c2 { background: #0f8a5f; } .wallet.c3 { background: #6250d6; } .wallet.c4 { background: #b5480f; }
.wallet .w-top { display: flex; justify-content: space-between; font-size: 14px; color: rgba(255,255,255,.85); }
.wallet .w-bal { font-size: 28px; font-weight: 800; margin: 8px 0 2px; } .wallet .w-bal small { font-size: 13px; font-weight: 400; font-family: Somar; opacity: .8; }
.wallet .w-sub { font-size: 12.5px; color: rgba(255,255,255,.7); }
.wallet .w-io { display: flex; gap: 12px; font-size: 12.5px; margin-top: 10px; padding-top: 8px; border-top: 1px solid rgba(255,255,255,.18); color: rgba(255,255,255,.85); }
.actions { display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px; margin-bottom: 14px; }
.act { background: #fff; border: 1px solid var(--line); border-radius: 14px; padding: 12px; display: flex; gap: 10px; align-items: center; font-size: 14px; font-weight: 700; cursor: pointer; text-align: right; font-family: inherit; color: var(--ink); }
.act:hover { border-color: #c9cdd8; }
.act small { display: block; font-weight: 400; font-size: 12px; color: var(--ink-3); }
.act .ai { width: 36px; height: 36px; border-radius: 10px; display: grid; place-items: center; flex: none; font-size: 17px; }
.ai.in { background: var(--up-bg); color: var(--up); } .ai.out { background: var(--down-bg); color: var(--down); } .ai.x { background: var(--s1-bg); color: var(--s1); } .ai.n { background: var(--line-soft); color: var(--ink-2); }
.led td { padding: 10px 8px; }
.led .ti { width: 32px; height: 32px; border-radius: 9px; display: grid; place-items: center; font-size: 15px; }
.day-h { display: flex; justify-content: space-between; padding: 10px 16px; background: #fafbfc; border-bottom: 1px solid var(--line-soft); font-size: 13.5px; flex-wrap: wrap; gap: 8px; }
.day-h .io { display: flex; gap: 14px; }
.close-card .count { background: var(--line-soft); border-radius: 12px; padding: 12px; margin-top: 12px; }
.pt { background: #fafbfc; border: 1px solid var(--line); border-radius: 14px; padding: 14px; }
.pt .nm { display: flex; align-items: center; gap: 8px; font-weight: 700; margin-bottom: 8px; }
.pt.surplus { background: var(--up-bg); border-color: #bfe3d2; }
.pt.surplus .v { font-size: 28px; font-weight: 800; color: var(--up); }
@media (max-width: 1100px) { .actions { grid-template-columns: repeat(3, 1fr); } }
</style>
@endpush
<div class="top">
    <div><h1>المالية</h1><div class="sub">{{ $period->preset === 'day' ? \App\Services\Period::DAYS[now()->dayOfWeek] . ' ' . now()->day . ' ' . \App\Services\Period::MONTHS[now()->month - 1] . ' ' . now()->year : $period->label }}</div></div>
    <div class="acts">@include('sg.partials.period')<button class="sg-burger">☰</button></div>
</div>
<nav class="tabs">
    <a href="{{ route('sg.finance', ['tab' => 'safe', 'p' => request('p')]) }}" class="{{ $tab === 'safe' ? 'on' : '' }}">الخزينة</a>
    @can('profit-view')<a href="{{ route('sg.finance', ['tab' => 'profit', 'p' => request('p')]) }}" class="{{ $tab === 'profit' ? 'on' : '' }}">الأرباح والشركاء</a>@endcan
    <a href="{{ route('sg.finance', ['tab' => 'usd', 'p' => request('p')]) }}" class="{{ $tab === 'usd' ? 'on' : '' }}">شراء دولار</a>
    @can('purchase-list')<a href="{{ route('sg.expenses', ['p' => request('p')]) }}">المصاريف</a>@endcan
    <a href="{{ route('sg.finance', ['tab' => 'ship', 'p' => request('p')]) }}" class="{{ $tab === 'ship' ? 'on' : '' }}">مصروفات الشحن</a>
    @can('dept-list')<a href="{{ route('sg.debts') }}">ديون العملاء</a>@endcan
</nav>
