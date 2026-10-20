<?php

namespace App\Http\Controllers\Sg;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Platform;
use App\Services\AppSettings;
use App\Services\Csv;
use App\Services\MessageService;
use App\Services\Period;
use App\Services\ShippingService as SS;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** الشحن فقط: الصين / دبي / أمريكا */
class ShippingController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:shipping-list', ['only' => ['index']]);
        $this->middleware('permission:shipping-create|shipping-edit', ['only' => ['create', 'store']]);
        $this->middleware('permission:shipping-edit', ['only' => ['edit', 'update', 'stage', 'ready', 'readyPreview']]);
        $this->middleware('permission:system-edit', ['only' => ['prices', 'savePrices', 'saveCard']]);
        $this->middleware('permission:shipping-edit', ['only' => ['addresses', 'sendAddresses', 'cardPreview']]);
    }

    protected function base()
    {
        return Order::with(['customer'])->where('kind', 'shipping');
    }

    public function index(Request $request)
    {
        $p = Period::make($request->input('p', 'month'));
        $q = $this->base();
        if ($request->filled('origin')) {
            $q->where('ship_origin', $request->origin);
        }
        if ($request->filled('q')) {
            $t = trim($request->q);
            $q->where(fn ($w) => \App\Support\Search::code($w, $t)->orWhere('tracking_no', 'like', "%$t%")
                ->orWhereHas('customer', fn ($c) => \App\Support\Search::customer($c, $t)));
        }
        $all = (clone $q)->latest()->get();
        $stage = $request->input('stage');
        $rows = $stage ? $all->filter(fn ($o) => SS::stageOf($o) === $stage)->values() : $all->where('status', '!=', 'canceld')->values();
        if ($request->export === 'csv') {
            return Csv::download('shipping.csv', ['الشحنة', 'التتبع', 'العميل', 'الكود', 'البلد', 'طرود', 'الوزن', 'سعر الكيلو $', 'الشحن $', 'الشحن د.ل', 'الحالة', 'الرف', 'المتبقي'],
                $rows->map(fn ($o) => [$o->code, $o->tracking_no, optional($o->customer)->name, optional($o->customer)->code, AppSettings::origins()[$o->ship_origin]['name'] ?? '', $o->bags_num, $o->kilo, $o->usd_per_kg - $o->usd_discount, $o->usd_amount, $o->ship_price, SS::STAGES[SS::stageOf($o)][0], $o->storage_num, max(0, $o->remaining())]));
        }
        $month = $this->base()->whereBetween('created_at', [$p->from, $p->to])->where('status', '!=', 'canceld')->get();
        $live = $all->where('status', '!=', 'canceld');

        return view('sg.shipping.index', [
            'nav' => 'ship', 'period' => $p, 'rows' => $rows, 'origins' => AppSettings::origins(),
            'counts' => collect(SS::STAGES)->map(fn ($v, $k) => $live->filter(fn ($o) => SS::stageOf($o) === $k)->count())->all() + ['all' => $live->count()],
            'byOrigin' => $live->filter(fn ($o) => in_array(SS::stageOf($o), ['warehouse', 'transit', 'expected']))->groupBy('ship_origin')->map->count(),
            'kpi' => [
                'count' => $month->count(), 'kg' => $month->sum('kilo'), 'customers' => $month->pluck('customer_id')->unique()->count(),
                'usd' => $month->sum('usd_amount'), 'lyd' => $month->sum('ship_price'),
                'abroad' => $live->filter(fn ($o) => in_array(SS::stageOf($o), ['warehouse', 'expected']))->count(),
                'transit' => $live->filter(fn ($o) => SS::stageOf($o) === 'transit')->count(),
                'shelf' => $live->filter(fn ($o) => SS::stageOf($o) === 'arrived')->count(),
                'shelfOld' => $live->filter(fn ($o) => SS::stageOf($o) === 'arrived' && ($o->shelfDays() ?? 0) > (int) AppSettings::get('shelf_reminder_days', 5))->count(),
            ],
            'shelves' => AppSettings::shelves(),
        ]);
    }

    protected function formData(?Order $o = null): array
    {
        return [
            'nav' => 'ship', 'o' => $o, 'origins' => AppSettings::origins(true), 'rate' => SS::rate(), 'rates' => SS::rates(),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'code', 'phone_1', 'ship_prices']),
            'types' => (array) AppSettings::get('ship_modes', ['جوي', 'بحري']), 'shelves' => AppSettings::shelves(),
            'platforms' => Platform::visible()->where('name', '!=', SS::PLATFORM)->orderBy('name')->pluck('name')->all(),
            'next' => $o ? $o->code : \App\Services\OrderNumber::nextShipping(),
        ];
    }

    public function create()
    {
        return view('sg.shipping.form', $this->formData());
    }

    public function edit(Order $order)
    {
        abort_unless($order->kind === 'shipping', 404);

        return view('sg.shipping.form', $this->formData($order->load('customer')));
    }

    protected function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id', 'new_name' => 'nullable|string|max:120', 'new_phone' => 'nullable|string|max:30', 'new_phone2' => 'nullable|string|max:30', 'new_address' => 'nullable|string|max:190',
            'ship_usd' => 'nullable|numeric|min:0', 'ship_lyd' => 'nullable|numeric|min:0', 'ship_amt' => 'nullable|numeric|min:0', 'ship_cur' => 'nullable|in:usd,lyd', 'ship_pay' => 'nullable|in:cash,bank', 'ship_platform' => 'nullable|string|max:80', 'origin' => 'required|string|max:10', 'tracking_no' => 'nullable|string|max:80',
            'bags' => 'nullable|integer|min:1|max:999', 'package_type' => 'nullable|string|max:60', 'kilo' => 'nullable|numeric|min:0|max:100000',
            'per_kg' => 'nullable|numeric|min:0', 'discount' => 'nullable|numeric|min:0', 'stage' => 'nullable|in:expected,warehouse,transit,arrived',
            'storage' => 'nullable|string|max:30', 'note' => 'nullable|string|max:500', 'send_whatsapp' => 'nullable|boolean',
        ];
    }

    /** حساب جديد انفتح توا (نبعتوله بيانات الدخول مع رسالة الشحنة) */
    protected bool $newAcc = false;

    /** العميل: مختار من القائمة، ولا جديد (اسم + رقم) — لو الرقم موجود يتربط بحسابه */
    protected function customerFrom(array $d): Customer
    {
        if (!empty($d['customer_id'])) {
            return Customer::findOrFail($d['customer_id']);
        }
        if (empty($d['new_phone']) || empty($d['new_name'])) {
            throw new \RuntimeException('اختار العميل، ولا اكتب اسم ورقم العميل الجديد');
        }
        [$c, $isNew] = \App\Support\GuestAccount::resolve($d['new_name'], $d['new_phone'], $d['new_address'] ?? null);
        if (!$c) {
            throw new \RuntimeException('رقم العميل الجديد مش صحيح');
        }
        if (!empty($d['new_phone2']) && empty($c->phone_2)) {
            $c->phone_2 = \App\Http\Controllers\Portal\SignupController::normalizePhone((string) $d['new_phone2']);
            $c->save();
        }
        $this->newAcc = $isNew;

        return $c;
    }

    /** 👁 معاينة رسالة «جاهز للاستلام» قبل ما تمشي (تتعدل في النافذة) */
    public function readyPreview(Order $order)
    {
        abort_unless($order->kind === 'shipping', 404);
        $order->load('customer');

        return response()->json(['text' => SS::arrivedText($order), 'phone' => optional($order->customer)->phone_1, 'name' => optional($order->customer)->name,
            'sent' => (bool) $order->arrived_notified_at]);
    }

    /** ✅ جاهز للاستلام: الحالة تولي «جاهز للاستلام» + رسالة «وصل طلبك» + ملف PDF للعميل */
    public function ready(Request $request, Order $order)
    {
        abort_unless($order->kind === 'shipping', 404);
        SS::applyStage($order, 'arrived', $request->input('storage') ?: null);
        $order->trip_status = \App\Models\Status::where('stage', 'arrived')->orderBy('sort')->value('name') ?: 'جاهز للاستلام';
        $order->save();
        \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');
        $sent = false;
        if ($order->customer && !$request->boolean('no_msg')) {
            $sent = SS::sendArrived($order->fresh(['customer']), $request->input('text'));
            if ($sent) {
                $order->arrived_notified_at = now();
                $order->save();
            }
        }
        \App\Models\Log::create(['user_id' => auth()->id(), 'type' => 'order', 'title' => ' شحنة جاهزة للاستلام (' . $order->code . ')', 'log' => $sent ? ' وصلت الرسالة + PDF للعميل' : ' بدون رسالة']);

        return back()->with($sent ? 'success' : 'warning', '✅ الشحنة ' . $order->code . ' جاهزة للاستلام' . ($sent ? ' · وصل العميل الرسالة والـ PDF' : ' · الرسالة ما وصلتش (تأكد من رقم العميل وجلسة واتسي)'));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules(), ['customer_id.required' => 'اختر العميل']);
        DB::beginTransaction();
        try {
            $o = SS::save(null, $this->customerFrom($data), $data);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        }
        if ($request->boolean('send_whatsapp')) {
            // 📨 رسالة تسجيل الشحنة — ولو العميل جديد نزيدوله تحتها بيانات الدخول لحسابه
            $tpl = MessageService::template('ship_registered');
            $txt = $tpl && ($tpl->enabled ?? true) ? MessageService::render($tpl->message, SS::vars($o)) : '';
            if ($this->newAcc && $o->customer) {
                $txt = trim($txt . "\n\n" . \App\Support\GuestAccount::accountText($o->customer, true));
            }
            if ($txt !== '' && $o->customer) {
                MessageService::sendText('ship_registered', $txt, array_values(array_filter([$o->customer->phone_1, $o->customer->phone_2])), $o->customer, $o);
            }
        }

        return redirect()->route('sg.shipping')->with('success', 'تم تسجيل الشحنة ' . $o->code . ' · ' . \App\Services\TreasuryService::fmt($o->usd_amount) . '$');
    }

    public function update(Request $request, Order $order)
    {
        abort_unless($order->kind === 'shipping', 404);
        $data = $request->validate($this->rules());
        DB::beginTransaction();
        try {
            SS::save($order, $this->customerFrom($data), $data);
            DB::commit();
        } catch (\RuntimeException $e) {
            DB::rollBack();

            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('sg.shipping')->with('success', 'تم حفظ الشحنة ' . $order->code);
    }

    /** تغيير حالة شحنة أو عدة شحنات */
    public function stage(Request $request)
    {
        $data = $request->validate(['ids' => 'required|array|min:1', 'ids.*' => 'integer', 'stage' => 'required|in:warehouse,transit,arrived', 'storage' => 'nullable|string|max:30', 'send_whatsapp' => 'nullable|boolean']);
        $n = 0;
        foreach ($this->base()->whereIn('id', $data['ids'])->where('delivered', false)->get() as $o) {
            SS::applyStage($o, $data['stage'], count($data['ids']) === 1 ? ($data['storage'] ?? null) : null);
            $o->save();
            $n++;
            // رسالة الوصول لا تُرسل مباشرة: تُراجع أولاً (البيانات والنص) من صفحة «مراجعة الرسائل»
            if ($request->boolean('send_whatsapp') && $data['stage'] !== 'arrived') {
                MessageService::sendTemplate('ship_stage', $o->customer, SS::vars($o), $o);
            }
        }
        \Illuminate\Support\Facades\Cache::forget('sg.badge.ready');
        if ($data['stage'] === 'arrived' && $request->boolean('send_whatsapp')) {
            return redirect()->route('sg.shipping.notify')->with('success', 'تم تحديث ' . $n . ' شحنة إلى: وصلت · راجع الرسائل قبل الإرسال');
        }

        return back()->with('success', 'تم تحديث ' . $n . ' شحنة إلى: ' . SS::STAGES[$data['stage']][0]);
    }

    public function prices()
    {
        $special = Customer::whereNotNull('ship_prices')->where('ship_prices', '!=', '')->where('ship_prices', '!=', '[]')->orderBy('name')->get();

        return view('sg.shipping.prices', [
            'nav' => 'ship', 'origins' => AppSettings::origins(), 'special' => $special,
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'code']),
            'platforms' => Platform::visible()->orderBy('id')->get(), 'notes' => (array) AppSettings::get('platform_notes', []), 'rate' => SS::rate(), 'rates' => SS::rates(),
        ]);
    }

    public function savePrices(Request $request)
    {
        $data = $request->validate([
            'og' => 'nullable|array', 'og.*.name' => 'required|string|max:40', 'og.*.flag' => 'nullable|string|max:16', 'og.*.city' => 'nullable|string|max:60',
            'og.*.per_kg' => 'nullable|numeric|min:0', 'og.*.addrs' => 'nullable|array', 'og.*.addrs.*.mode' => 'nullable|in:جوي,بحري', 'og.*.addrs.*.address' => 'nullable|string|max:600', 'og.*.addrs.*.per_kg' => 'nullable|numeric|min:0', 'og.*.min' => 'nullable|numeric|min:0', 'og.*.days' => 'nullable|string|max:40', 'og.*.address' => 'nullable|string|max:600', 'og.*.tiers' => 'nullable|string|max:300',
            'new_key' => 'nullable|alpha_dash|max:10', 'new_name' => 'nullable|string|max:40',
            'cp' => 'nullable|array', 'new_customer' => 'nullable|exists:customers,id',
            'pf' => 'nullable|array', 'pf.*.rate' => 'nullable|numeric|min:0', 'pf.*.note' => 'nullable|string|max:120',
            'rate_cash' => 'nullable|numeric|min:0', 'rate_bank' => 'nullable|numeric|min:0',
        ]);
        $origins = [];
        foreach ((array) ($data['og'] ?? []) as $key => $o) {
            // 📍 عناوين البلد: كل واحد (جوي/بحري) بعنوانه وسعره ومفعّل ولا لا
            $addrs = [];
            foreach ((array) ($o['addrs'] ?? []) as $i => $a) {
                $txt = trim(str_replace("\r\n", "\n", (string) ($a['address'] ?? '')));
                if ($txt === '') {
                    continue;
                }
                $addrs[] = ['mode' => ($a['mode'] ?? 'جوي') === 'بحري' ? 'بحري' : 'جوي', 'address' => $txt, 'per_kg' => (float) ($a['per_kg'] ?? 0),
                    'active' => $request->boolean("og.$key.addrs.$i.active")];
            }
            $first = collect($addrs)->firstWhere('active', true) ?? ($addrs[0] ?? null);
            $origins[] = ['key' => $key, 'name' => $o['name'], 'flag' => $o['flag'] ?? '', 'city' => $o['city'] ?? '',
                'per_kg' => $first ? (float) $first['per_kg'] : (float) ($o['per_kg'] ?? 0), 'min' => (float) ($o['min'] ?? 0),
                'days' => $o['days'] ?? '', 'address' => $first ? $first['address'] : str_replace("\r\n", "\n", (string) ($o['address'] ?? '')), 'addrs' => $addrs,
                'tiers' => trim((string) ($o['tiers'] ?? '')), 'active' => $request->boolean("og.$key.active")];
        }
        if (!empty($data['new_key']) && !empty($data['new_name'])) {
            $origins[] = ['key' => strtolower($data['new_key']), 'name' => $data['new_name'], 'flag' => '🌍', 'city' => '', 'per_kg' => 0, 'min' => 0, 'days' => '', 'address' => "{الكود} {الاسم}\n", 'active' => true];
        }
        $notes = [];
        foreach ((array) ($data['pf'] ?? []) as $id => $pf) {
            if ($p = Platform::find($id)) {
                if (isset($pf['rate']) && $pf['rate'] !== '') {
                    $p->rate = $pf['rate'];
                    $p->save();
                }
                $notes[$id] = $pf['note'] ?? '';
            }
        }
        AppSettings::put(['ship_origins' => $origins ?: AppSettings::get('ship_origins'), 'platform_notes' => $notes,
            // 💱 سعر صرف الشحن: كاش ومصرف (فارغ = آخر سعر صرف في المنظومة)
            'ship_rate_cash' => (float) ($data['rate_cash'] ?? 0), 'ship_rate_bank' => (float) ($data['rate_bank'] ?? 0)]);
        // الأسعار الخاصة
        foreach ((array) $request->input('cp', []) as $cid => $vals) {
            if ($c = Customer::find($cid)) {
                $clean = collect((array) $vals)->filter(fn ($v) => $v !== null && $v !== '' && is_numeric($v))->map(fn ($v) => (float) $v)->all();
                $c->ship_prices = $clean ? json_encode($clean) : null;
                $c->save();
            }
        }
        if (!empty($data['new_customer'])) {
            $c = Customer::find($data['new_customer']);
            if ($c && !$c->ship_prices) {
                $c->ship_prices = json_encode(['_' => 1]);
                $c->save();
            }
        }

        return back()->with('success', 'تم حفظ أسعار الشحن');
    }

    /** الصورة الثابتة اللي ينكتب عليها كود العميل */
    public function saveCard(Request $request)
    {
        $data = $request->validate(['bg' => 'nullable|image|mimes:jpg,jpeg,png|max:4096', 'x' => 'required|numeric|min:0|max:100', 'y' => 'required|numeric|min:0|max:100',
            'size' => 'required|numeric|min:1|max:40', 'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/']]);
        $cfg = (array) AppSettings::get('ship_card', []);
        if ($request->hasFile('bg')) {
            $cfg['bg'] = $request->file('bg')->store('ship-card', 'local');
        }
        $cfg = array_merge($cfg, ['x' => (float) $data['x'], 'y' => (float) $data['y'], 'size' => (float) $data['size'], 'color' => $data['color']]);
        AppSettings::put(['ship_card' => $cfg]);

        return back()->with('success', 'تم حفظ صورة الكود');
    }

    public function cardPreview(Request $request)
    {
        $c = Customer::where('code', $request->input('code'))->first() ?? tap(new Customer, fn ($x) => $x->code = $request->input('code', '1234'));
        $path = SS::cardImage($c);
        if (!$path) {
            abort(404, 'ارفع الصورة الثابتة أول');
        }
        $bytes = \Illuminate\Support\Facades\Storage::disk('local')->get($path);
        \Illuminate\Support\Facades\Storage::disk('local')->delete($path);

        return response($bytes, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'no-store']);
    }

    /** إرسال عناوين الشحن للعميل: كل عنوان رسالة بروحها */
    public function addresses(Request $request)
    {
        return view('sg.shipping.addresses', ['nav' => 'ship', 'origins' => AppSettings::origins(true),
            'customers' => Customer::orderBy('name')->get(['id', 'name', 'code', 'phone_1', 'ship_prices']),
            'hasCard' => !empty(((array) AppSettings::get('ship_card', []))['bg'])]);
    }

    public function sendAddresses(Request $request)
    {
        $data = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'name' => 'required_without:customer_id|nullable|string|max:100',
            'phone' => 'required_without:customer_id|nullable|string|max:20',
            'code' => 'nullable|string|max:20',
            'origins' => 'required|array|min:1', 'price' => 'nullable|array', 'price.*' => 'nullable|numeric|min:0',
            'with_image' => 'nullable|boolean',
        ], ['origins.required' => 'اختار عنوان واحد على الأقل', 'name.required_without' => 'اختار عميل أو اكتب اسم عميل جديد']);
        $all = AppSettings::origins(true);
        if (!empty($data['customer_id'])) {
            $c = Customer::find($data['customer_id']);
        } else {
            $phone = preg_replace('/\D/', '', $data['phone']);
            if (Customer::where('phone_1', $data['phone'])->exists()) {
                return back()->withInput()->with('error', 'الرقم هذا مسجل لعميل — اختاره من القائمة');
            }
            $code = trim((string) ($data['code'] ?? ''));
            if ($code !== '' && Customer::where('code', $code)->exists()) {
                return back()->withInput()->with('error', 'الكود ' . $code . ' مستخدم');
            }
            if ($code === '') {
                $n = 4;
                do {
                    $code = substr($phone, -$n++);
                } while (Customer::where('code', $code)->exists() && $n <= strlen($phone));
            }
            $c = new Customer;
            $c->name = $data['name'];
            $c->phone_1 = $data['phone'];
            $c->code = $code;
            $c->save();
        }
        // الأسعار الخاصة: لو أقل من سعر البلد تتسجل للعميل
        $sp = json_decode((string) $c->ship_prices, true) ?: [];
        foreach ((array) ($data['price'] ?? []) as $k => $v) {
            if (isset($all[$k]) && $v !== null && $v !== '' && abs((float) $v - (float) $all[$k]['per_kg']) > 0.0001) {
                $sp[$k] = (float) $v;
            }
        }
        unset($sp['_']);
        $c->ship_prices = $sp ? json_encode($sp) : null;
        $c->save();

        $img = $request->boolean('with_image') ? SS::cardImage($c) : null;
        $phone = MessageService::normalize((string) $c->phone_1);
        $sent = 0;
        $first = trim(explode(' ', (string) $c->name)[0]);
        foreach ($data['origins'] as $k) {
            if (!isset($all[$k])) {
                continue;
            }
            $o = $all[$k];
            $tiers = collect(SS::tiers($o))->map(fn ($p, $kg) => "من {$kg} كغ: {$p}$")->implode(' · ');
            // كل عنوان مفعّل (✈️ جوي / 🚢 بحري) بسعره
            $blocks = [];
            foreach (SS::addrs($o) as $a) {
                $base = (float) ($a['per_kg'] ?? $o['per_kg']);
                $mine = SS::perKg($c, $k, $a['mode'] ?? null);
                $pl = $mine < $base - 0.0001 ? "💰 سعر الكيلو: كان بـ {$base}$ وقعد بـ {$mine}$ لأنك عميل خاص 🌟" : "💰 سعر الكيلو: {$base}$";
                $blocks[] = (SS::MODES[$a['mode'] ?? 'جوي'] ?? '✈️') . ' *شحن ' . ($a['mode'] ?? 'جوي') . "*\n" . SS::addressFor($o, $c, $a) . "\n\n" . $pl;
            }
            $priceLine = '';
            $text = "اهلا {$first} 👋
عنوان الشحن من {$o['flag']} {$o['name']}:

" . implode("\n\n━━━━━━━━━━\n\n", $blocks)
                . ($tiers ? "
📉 تخفيض حسب الوزن: {$tiers}" : '')
                . (!empty($o['min']) ? "
📦 أقل شحنة: {$o['min']}$" : '') . (!empty($o['days']) ? "
⏱ المدة: {$o['days']}" : '')
                . "

🔖 كودك: SG{$c->code}";
            try {
                $ok = app(\App\Services\WhatsAppService::class)->sendMessage($phone, $text, null, [], $img);
            } catch (\Throwable $e) {
                report($e);
                $ok = false;
            }
            $img = null; // الصورة مع أول رسالة بس
            \App\Models\CustomerMessage::create(['customer_id' => $c->id, 'body' => $text, 'sent' => $ok, 'created_id' => auth()->id()]);
            $sent += $ok ? 1 : 0;
        }
        \App\Models\Log::create(['user_id' => auth()->id(), 'type' => 'customer', 'title' => ' عناوين شحن (' . $c->name . ')', 'log' => ' أُرسل ' . $sent . ' من ' . count($data['origins'])]);

        return back()->with($sent ? 'success' : 'warning', $sent ? "أُرسل لـ {$c->name} {$sent} عنوان · كوده SG{$c->code}" : 'العميل تسجل بس الواتساب ما وصلش');
    }

    protected function pendingNotify()
    {
        return $this->base()->where('arrived', true)->where('delivered', false)->whereNull('arrived_notified_at')->orderBy('id')->get();
    }

    public function notifyForm()
    {
        $orders = $this->pendingNotify();
        [$tpl, $previews] = \App\Services\ArrivalNotify::previews($orders);

        return view('sg.trips.notify', ['nav' => 'ship', 'trip' => null, 'orders' => $orders, 'tpl' => $tpl, 'previews' => $previews,
            'sentCount' => $this->base()->whereNotNull('arrived_notified_at')->count()]);
    }

    public function notifySend(Request $request)
    {
        return \App\Services\ArrivalNotify::flash(\App\Services\ArrivalNotify::send($request, $this->pendingNotify()));
    }
}
