<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Log;
use App\Models\Order;
use App\Models\User;
use App\Services\AppSettings;
use App\Services\CourierService;
use App\Services\MessageService;
use App\Services\TreasuryService as TS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** 🛵 تجهيز طلعة: نختارو الجاهز، نعطوه لمندوب (بحساب أو بالاسم) أو لطابور خارج بنغازي، ونطبعو كشف PDF */
class DispatchController extends Controller
{
    public const ACTIVE = ['requested', 'assigned', 'out', 'problem'];

    public function __construct()
    {
        $this->middleware('permission:courier-list', ['only' => ['index', 'pdf']]);
        $this->middleware('permission:courier-edit', ['only' => ['assign', 'act']]);
    }

    protected function readyQuery()
    {
        $active = Delivery::whereIn('status', ['requested', 'assigned', 'out', 'problem'])->pluck('id');

        return Order::where('arrived', true)->where('delivered', false)->where('status', '!=', 'canceld')
            ->where(fn ($w) => $w->whereNull('delivery_id')->orWhereNotIn('delivery_id', $active));
    }

    public function index(Request $request)
    {
        $q = trim((string) $request->q);
        $tab = $request->input('tab') === 'out' ? 'out' : 'in';
        $ready = $this->readyQuery()->with('customer')
            ->when($q !== '', function ($w) use ($q) {
                $ids = Customer::where(fn ($c) => $c->where('code', 'like', '%' . $q . '%')->orWhere('name', 'like', '%' . $q . '%')->orWhere('phone_1', 'like', '%' . $q . '%'))->pluck('id');
                $w->where(fn ($x) => $x->whereIn('customer_id', $ids)->orWhere('code', 'like', $q . '%'));
            })
            ->orderBy('arrived_at')->get()->groupBy('customer_id')->take(300);
        if (!\Illuminate\Support\Facades\Schema::hasColumn('deliveries', 'zone')) {
            return redirect()->route('sg.couriers')->with('error', 'لازم تفتح /system-update مرة وحدة باش تخدم صفحة طلبيات دليفري');
        }
        // كل التوصيلات المفتوحة — حتى طلبات العملاء من حساباتهم تطلع هني طول
        $all = Delivery::with(['customer', 'orders', 'courier'])->whereIn('status', self::ACTIVE)->latest('id')->get();
        $all->each(fn ($d) => $d->zone = $d->zone ?: (CourierService::outsideBenghazi($d->city) ? 'out' : 'in'));
        $queue = $all->where('zone', $tab)->values();

        return view('sg.dispatch.index', [
            'nav' => 'dispatch', 'q' => $q, 'tab' => $tab, 'ready' => $ready, 'queue' => $queue,
            'couriers' => CourierService::couriers()->where('status', true),
            'counts' => $all->countBy('zone'),
            'batches' => $queue->pluck('batch')->filter()->unique()->sortDesc()->values(),
            'defFee' => (float) AppSettings::get('courier_default_fee', 0),
        ]);
    }

    /** نعيّنو الزبائن المختارين: كل زبون = توصيل واحد */
    public function assign(Request $request)
    {
        $d = $request->validate([
            'c' => 'required|array|min:1', 'c.*' => 'integer',
            'kind' => 'nullable|array', 'kind.*' => 'in:order,deposit',
            'courier_id' => 'nullable|integer', 'courier_name' => 'nullable|string|max:100',
            'zone' => 'required|in:in,out', 'fee' => 'nullable|numeric|min:0',
        ]);
        $courier = !empty($d['courier_id']) ? User::where('type_id', 4)->find($d['courier_id']) : null;
        $name = trim((string) ($d['courier_name'] ?? ''));
        if (!$courier && $name === '' && $d['zone'] === 'in') {
            return back()->with('error', 'اختار مندوب ولا اكتب اسمه');
        }
        $batch = 'T-' . now()->format('md-Hi');
        $made = collect();
        DB::transaction(function () use ($d, $courier, $name, $batch, &$made) {
            foreach (array_unique($d['c']) as $cid) {
                $cu = Customer::find($cid);
                $orders = $cu ? $this->readyQuery()->where('customer_id', $cu->id)->get() : collect();
                if (!$cu || $orders->isEmpty()) {
                    continue;
                }
                $dl = CourierService::assign($cu, $orders, ['fee' => (float) ($d['fee'] ?? 0), 'city' => $d['zone'] === 'out' ? 'خارج بنغازي' : null], $courier);
                $dl->courier_name = $courier ? null : ($name !== '' ? $name : null);
                $dl->kind = ($d['kind'][$cid] ?? 'order') === 'deposit' ? 'deposit' : 'order';
                $dl->zone = $d['zone'];
                $dl->batch = $batch;
                if (!$courier && $name !== '') {
                    $dl->status = 'out';
                    $dl->assigned_at = now();
                }
                $dl->save();
                if (!$courier && $name !== '') {
                    $dl->log('out', 'طلعة ' . $batch . ' مع ' . $name);
                }
                $made->push($dl);
            }
        });
        if ($made->isEmpty()) {
            return back()->with('error', 'ما فيش طلبيات جاهزة للزبائن المختارين');
        }
        foreach ($made->where('kind', 'deposit') as $dl) {
            $this->depositMsg($dl);
        }
        Cache::forget('sg.badge.ready');
        Cache::forget('sg.badge.courier');

        return redirect()->route('sg.dispatch', ['tab' => $d['zone']])
            ->with('success', '✓ ' . $made->count() . ' زبون مع ' . ($courier ? $courier->name : ($name ?: 'طابور خارج بنغازي')))
            ->with('print_url', route('sg.dispatch.pdf', ['batch' => $batch]));
    }

    /** إجراء على توصيل في الطابور: نوع · تحويل · تغيير المندوب · سلّم · رجّع */
    public function act(Request $request, Delivery $delivery)
    {
        $d = $request->validate(['a' => 'required|in:kind,move,courier,done,back', 'courier_id' => 'nullable|integer', 'courier_name' => 'nullable|string|max:100']);
        abort_unless(in_array($delivery->status, self::ACTIVE, true), 422, 'التوصيل هذا مقفول');
        $cu = $delivery->customer;
        switch ($d['a']) {
            case 'kind':
                $delivery->kind = $delivery->kind === 'deposit' ? 'order' : 'deposit';
                $delivery->save();
                $delivery->log($delivery->status, 'النوع: ' . ($delivery->kind === 'deposit' ? 'عربون' : 'طلبية'));
                if ($delivery->kind === 'deposit') {
                    $this->depositMsg($delivery);
                }

                return back()->with('success', 'تحول لـ' . ($delivery->kind === 'deposit' ? 'عربون (انبعتله الرابط والتفاصيل)' : 'طلبية'));
            case 'move':
                $cur = $delivery->zone ?: (CourierService::outsideBenghazi($delivery->city) ? 'out' : 'in');
                $delivery->zone = $cur === 'out' ? 'in' : 'out';
                $delivery->save();
                $delivery->log($delivery->status, $delivery->zone === 'out' ? 'تحول لطابور خارج بنغازي' : 'تحول لطابور المندوب');

                return back()->with('success', 'تحول ' . ($delivery->zone === 'out' ? 'لخارج بنغازي' : 'للمندوب'));
            case 'courier':
                $c = !empty($d['courier_id']) ? User::where('type_id', 4)->find($d['courier_id']) : null;
                $n = trim((string) ($d['courier_name'] ?? ''));
                if (!$c && $n === '') {
                    return back()->with('error', 'اختار مندوب ولا اكتب اسمه');
                }
                if ($c) {
                    CourierService::setCourier($delivery, $c);
                    $delivery->courier_name = null;
                } else {
                    $delivery->courier_id = null;
                    $delivery->courier_name = $n;
                    $delivery->status = 'out';
                    $delivery->assigned_at = now();
                    $delivery->log('out', 'مع ' . $n);
                }
                $delivery->save();

                return back()->with('success', '✓ تغيّر المندوب');
            case 'back':
                CourierService::returnToShop($delivery, 'returned', 'رجعت من الطلعة');

                return back()->with('success', '↩ رجعت للرف');
            case 'done':
                // يتسلّم عند الكاشير: نفكو الإيصالات من التوصيل ونفتحو صفحة التسليم للزبون (الدفع والتسليم العادي)
                foreach ($delivery->orders as $o) {
                    if (!$o->delivered) {
                        $o->delivery_id = null;
                        $o->save();
                    }
                }
                $delivery->status = 'delivered';
                $delivery->delivered_at = now();
                $delivery->save();
                $delivery->log('delivered', 'سلّم عن طريق ' . ($delivery->courier_name ?: optional($delivery->courier)->name) . ' · الحساب عند الكاشير');
                Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' طلعة: تم (' . optional($cu)->name . ')', 'log' => ' ' . $delivery->code . ' · ' . $delivery->batch]);
                Cache::forget('sg.badge.ready');

                return redirect()->route('sg.delivery', ['q' => optional($cu)->code, 'cid' => optional($cu)->id])->with('success', '🛵 كمّل التسليم والدفع لـ' . optional($cu)->name);
        }

        return back();
    }

    /** 📄 كشف الطلعة PDF: كل عمود بيان والزبائن تحت بعض */
    public function pdf(Request $request)
    {
        $tab = $request->input('tab') === 'out' ? 'out' : 'in';
        $rows = Delivery::with(['customer', 'orders', 'courier'])
            ->when($request->filled('batch'), fn ($q) => $q->where('batch', $request->batch))
            ->when(!$request->filled('batch'), fn ($q) => $q->whereIn('status', self::ACTIVE))
            ->when($request->filled('ids'), fn ($q) => $q->whereIn('id', array_filter(array_map('intval', explode(',', (string) $request->ids)))))
            ->orderBy('id')->get();
        if (!$request->filled('batch')) {
            $rows = $rows->filter(fn ($d) => ($d->zone ?: (CourierService::outsideBenghazi($d->city) ? 'out' : 'in')) === $tab)->values();
        }
        abort_if($rows->isEmpty(), 404);
        $temp = storage_path('app/mpdf-temp');
        if (!is_dir($temp)) {
            mkdir($temp, 0700, true);
        }
        $fontDirs = (new \Mpdf\Config\ConfigVariables())->getDefaults()['fontDir'];
        $fontData = (new \Mpdf\Config\FontVariables())->getDefaults()['fontdata'];
        $pdf = new \Mpdf\Mpdf(['mode' => 'utf-8', 'format' => 'A4-L', 'orientation' => 'L',
            'fontDir' => array_merge($fontDirs, [resource_path('fonts/somar')]),
            'fontdata' => array_merge($fontData, ['somar' => ['R' => 'Somar-Regular.ttf', 'B' => 'Somar-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75]]),
            'tempDir' => $temp, 'default_font' => 'somar', 'default_font_size' => 10,
            'margin_top' => 8, 'margin_bottom' => 10, 'margin_left' => 8, 'margin_right' => 8, 'autoScriptToLang' => true, 'autoLangToFont' => false]);
        $pdf->SetDirectionality('rtl');
        $title = 'طلعة ' . ($request->batch ?: now()->format('Y-m-d'));
        $pdf->SetTitle($title);
        $pdf->WriteHTML(view('pdf.dispatch', ['rows' => $rows, 'title' => $title, 'f' => fn ($n) => TS::fmt($n)])->render());

        return response($pdf->Output('', \Mpdf\Output\Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="dispatch-' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($request->batch ?: now()->format('md'))) . '.pdf"',
        ]);
    }

    /** 💵 عربون: نبعتو للزبون الرابط والتفاصيل */
    protected function depositMsg(Delivery $dl): void
    {
        $c = $dl->customer;
        if (!$c || !$c->phone_1) {
            return;
        }
        $who = $dl->courier_name ?: optional($dl->courier)->name;
        $codes = $dl->orders->pluck('code')->implode('، ');
        $phone = CourierService::publicPhone($dl->courier);
        $url = rtrim((string) config('app.url'), '/');
        $text = "{$c->name} 👋\n🛵 المندوب" . ($who ? " *{$who}*" : '') . " جاي يقبض *العربون*\n"
            . "🔢 الإيصال: *{$codes}*\n💰 المبلغ: *" . TS::fmt($dl->amount) . " د.ل*"
            . ((float) $dl->fee > 0 ? "\n🚚 التوصيل: " . TS::fmt($dl->fee) . ' د.ل' : '')
            . ($phone ? "\n📞 المندوب: {$phone}" : '')
            . "\n\nتفاصيل طلبك من حسابك 👇\n{$url}";
        try {
            MessageService::sendText('deposit_link', $text, [$c->phone_1], $c);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
