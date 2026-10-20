<?php

namespace App\Http\Controllers;

use App\Http\Requests\ManualCartStoreRequest;
use App\Http\Requests\CartPdfUpdateRequest;
use App\Models\CartQuote;
use App\Models\Log;
use App\Models\Notif_orgs;
use App\Models\Setting;
use App\Models\WhatsappMessages;
use App\Services\CartQuoteService;
use App\Services\CartQuoteClient;
use App\Services\ManualCartService;
use App\Services\SheinQuotationPdf;
use App\Services\CartQuoteException;
use App\Services\CartWalletGuard;
use App\Services\CartWalletPausedException;
use App\Services\CartStatusAuditService;
use Illuminate\Http\Request;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:cart-list');
        $this->middleware('permission:cart-edit', ['only' => ['update', 'updatePdf', 'resetPdf']]);
        $this->middleware('permission:cart-create', ['only' => ['create', 'store']]);
    }

    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'operator_status' => ['nullable', Rule::in(array_keys(CartQuote::OPERATOR_STATUSES))],
            'per_page' => ['nullable', 'integer', Rule::in([10, 25, 50, 100])],
            'session_uuid' => ['nullable', 'string', 'regex:/^[a-fA-F0-9]{32}$/D'],
            'retailer' => ['nullable', Rule::in(array_keys(CartQuote::RETAILERS))],
        ]);
        $query = CartQuote::query();
        if (! empty($filters['retailer'])) {
            $query->where('retailer', $filters['retailer']);
        }
        $search = trim($filters['search'] ?? '');
        if (preg_match('/^#(\d+)$/D', $search, $matches)) {
            $query->where('id', $matches[1]);
        } elseif ($search !== '') {
            $query->where(function ($query) use ($search) {
                $query->where('source_url', 'like', '%'.$search.'%')
                    ->orWhereHas('incomingMessage', function ($incoming) use ($search) {
                        $incoming->where('name', 'like', '%'.$search.'%')->orWhere('from', 'like', '%'.$search.'%')
                            ->orWhere('contact_phone', 'like', '%'.$search.'%');
                    });
                if (ctype_digit($search)) {
                    $query->orWhere('id', $search);
                }
            });
        }
        if (! empty($filters['session_uuid'])) {
            $query->whereHas('incomingMessage', fn ($incoming) => $incoming->where('session_uuid', $filters['session_uuid']));
        }
        $counts = (clone $query)->selectRaw('operator_status, COUNT(*) AS total')
            ->groupBy('operator_status')->pluck('total', 'operator_status');
        if (! empty($filters['operator_status'])) {
            $query->where('operator_status', $filters['operator_status']);
        }
        $perPage = (int) ($filters['per_page'] ?? 25);
        $carts = $query->with('incomingMessage')->orderByDesc('id')->paginate($perPage)->withQueryString();
        $pdf = app(SheinQuotationPdf::class);
        $viewTotals = $carts->getCollection()->filter(fn ($cart) => $cart->quotation || $cart->retailer === 'amazon')
            ->mapWithKeys(function ($cart) use ($pdf) {
                if ($cart->retailer === 'amazon') {
                    $total = data_get($cart->amazon_pricing, 'total', ['amount' => null, 'currency' => 'LYD']);
                    return [$cart->id => $total + ['item_count' => data_get($cart->amazon_pricing, 'quantity', $cart->requested_quantity)]];
                }
                $quotation = $pdf->quotationForPdf($cart);
                return [$cart->id => $quotation['total'] + ['item_count' => $quotation['item_count']]];
            });
        $main_notifications = $this->notifications();
        $settings = Setting::find(1);
        $walletPauseEnabled = $settings?->shein_pause_on_empty_wallet ?? true;
        $storeStates = [];
        foreach (array_keys(CartQuote::RETAILERS) as $store) {
            $storeStates[$store] = $settings?->isCartStoreEnabled($store) ?? true;
        }
        $receivingSessions = $this->receivingSessions();

        // الشكل الجديد (تجربة لين يتعتمد): /app/carts — نفس البيانات ونفس الفلاتر
        if ($request->routeIs('sg.carts')) {
            $nav = 'cart';

            return view('sg.carts.index', compact('carts', 'counts', 'filters', 'walletPauseEnabled', 'perPage', 'viewTotals', 'receivingSessions', 'storeStates', 'nav'));
        }

        return view('admin.carts.index', compact('carts', 'counts', 'filters', 'main_notifications', 'walletPauseEnabled', 'perPage', 'viewTotals', 'receivingSessions', 'storeStates'));
    }

    public function create(Request $request)
    {
        $validated = $request->validate(['retailer' => ['nullable', Rule::in(array_keys(CartQuote::RETAILERS))]]);
        $retailer = $validated['retailer'] ?? 'shein';
        $storeEnabled = Setting::find(1)?->isCartStoreEnabled($retailer) ?? true;
        $submissionKey = (string) Str::uuid();
        $main_notifications = $this->notifications();

        return view('admin.carts.create', compact('submissionKey', 'main_notifications', 'retailer', 'storeEnabled'));
    }

    public function wallet(CartQuoteClient $client)
    {
        $pauseEnabled = Setting::find(1)?->shein_pause_on_empty_wallet ?? true;
        try {
            $wallet = $client->wallet();
            $result = ['wallet' => $wallet, 'checked_at' => now()->toIso8601String(),
                'cart_replies_paused' => $pauseEnabled && ! CartWalletGuard::hasCredit($wallet)];
            $status = 200;
        } catch (CartQuoteException | ConnectionException $e) {
            $result = ['wallet' => null, 'message' => 'تعذر جلب رصيد المحفظة. حاول التحديث مجدداً.',
                'cart_replies_paused' => $pauseEnabled];
            $status = 503;
        }
        return response()->json($result, $status)->header('Cache-Control', 'no-store, private');
    }

    public function store(ManualCartStoreRequest $request, ManualCartService $manualCarts, CartQuoteService $quotes)
    {
        $validated = $request->validated();
        $cart = $manualCarts->create($validated['source_url'], $validated['phone'], $validated['submission_key'], auth()->id(),
            $validated['retailer'] ?? 'shein', (int) ($validated['requested_quantity'] ?? 1));
        $quotes->process($cart->id);
        $cart->refresh();

        if (in_array($cart->last_error, [CartWalletPausedException::EMPTY, CartWalletPausedException::INSUFFICIENT], true)) {
            return redirect()->route('carts.show', $cart)->with('warning', 'تم حفظ السلة وستتم معالجتها تلقائياً بعد شحن المحفظة.');
        }

        if ($cart->retailer === 'amazon') {
            $flash = $cart->status === 'failed' ? 'error' : ($cart->last_error ? 'warning' : 'success');
            $message = $cart->status === 'failed' ? 'تم حفظ منتج أمازون، لكن تعذر تسعيره. راجع تفاصيل العرض.'
                : ($cart->last_error ? 'تم حفظ منتج أمازون وستتم إعادة محاولة تسعيره تلقائياً.' : 'تمت إضافة منتج أمازون وطلب تسعيره بنجاح.');
            return redirect()->route('carts.show', $cart)->with($flash, $message);
        }

        if ($cart->status === 'failed') {
            return redirect()->route('carts.show', $cart)->with('error', 'تم حفظ السلة، لكن تعذر تجهيز عرض السعر. راجع تفاصيل السلة.');
        }
        if ($cart->last_error) {
            return redirect()->route('carts.show', $cart)->with('warning', 'تم حفظ السلة. تعذر طلب عرض السعر حالياً وستتم إعادة المحاولة تلقائياً.');
        }
        return redirect()->route('carts.show', $cart)->with('success', 'تمت إضافة السلة إلى القائمة وطلب فحصها بنجاح.');
    }

    public function show(CartQuote $cart, SheinQuotationPdf $pdf)
    {
        $cart->load(['incomingMessage.replyMessage', 'handledBy']);
        $main_notifications = $this->notifications();
        $quotationError = null;
        if ($cart->status === 'succeeded') {
            try {
                $cart = $pdf->prepare($cart)->load(['incomingMessage.replyMessage', 'handledBy']);
            } catch (CartQuoteException $e) {
                $quotationError = $e->getMessage();
            }
        }

        $pdfQuotation = $cart->quotation ? $pdf->quotationForPdf($cart) : null;
        $customerTotal = $cart->retailer === 'amazon' ? data_get($cart->amazon_pricing, 'total')
            : data_get($pdfQuotation, 'total', data_get($cart->payload, 'result.total'));
        $customerItemCount = $cart->retailer === 'amazon' ? data_get($cart->amazon_pricing, 'quantity', $cart->requested_quantity)
            : data_get($pdfQuotation, 'item_count', data_get($cart->payload, 'result.itemCount', '—'));
        $priceDisplay = $pdfQuotation ? app(\App\Services\SheinQuotationCalculator::class)->presentation($pdfQuotation) : null;
        $pdfEditor = $cart->quotation ? $pdf->editorValues($cart) : null;
        $pdfRevision = $pdf->revision($cart);
        $statusAudits = $cart->statusAudits()->paginate(20, ['*'], 'history_page')->withQueryString();
        $latestStatusAudit = $cart->statusAudits()->first();
        $receivingSessions = $this->receivingSessions();

        return view('admin.carts.show', compact('cart', 'main_notifications', 'quotationError', 'pdfQuotation', 'priceDisplay', 'pdfEditor', 'pdfRevision', 'statusAudits', 'latestStatusAudit', 'receivingSessions', 'customerTotal', 'customerItemCount'));
    }

    private function receivingSessions(): array
    {
        $sessions = [];
        foreach (Setting::find(1)?->whatsapp_numbers ?? [] as $session) {
            $uuid = $session['session_uuid'] ?? null;
            if (is_string($uuid) && preg_match('/^[a-fA-F0-9]{32}$/D', $uuid) === 1) {
                $sessions[$uuid] = ['name' => $session['name'] ?? null, 'phone' => null];
            }
        }
        $latest = DB::table('whatsapp_messagess')->selectRaw('MAX(id) AS id')->whereNotNull('session_uuid')->groupBy('session_uuid');
        foreach (WhatsappMessages::whereIn('id', $latest)->orderByDesc('id')->get(['id', 'session_uuid', 'session_name', 'receiving_phone']) as $message) {
            $uuid = $message->session_uuid;
            if (preg_match('/^[a-fA-F0-9]{32}$/D', $uuid) !== 1) continue;
            $sessions[$uuid] = ['name' => $message->session_name ?? $sessions[$uuid]['name'] ?? null,
                'phone' => $message->receiving_phone];
        }
        return $sessions;
    }

    public function productImage(CartQuote $cart, int $index, SheinQuotationPdf $pdf)
    {
        $path = $pdf->productImage($cart, $index);
        abort_unless($path, 404);
        return response()->file($path, ['Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function updatePdf(CartPdfUpdateRequest $request, CartQuote $cart, SheinQuotationPdf $pdf)
    {
        $values = $request->validated();
        $revision = $values['revision'];
        unset($values['revision']);
        return $this->savePdfOverrides($cart, $pdf, $revision, $values);
    }

    public function resetPdf(Request $request, CartQuote $cart, SheinQuotationPdf $pdf)
    {
        $values = $request->validate(['revision' => ['required', 'string', 'size:64']]);
        return $this->savePdfOverrides($cart, $pdf, $values['revision'], null);
    }

    private function savePdfOverrides(CartQuote $cart, SheinQuotationPdf $pdf, string $revision, ?array $values)
    {
        DB::transaction(function () use ($cart, $pdf, $revision, $values) {
            $record = CartQuote::lockForUpdate()->findOrFail($cart->id);
            abort_unless($record->status === 'succeeded' && $record->quotation, 409, 'Quotation is not ready');
            if (! hash_equals($pdf->revision($record), $revision)) {
                throw ValidationException::withMessages(['revision' => 'تم تعديل العرض أثناء فتح الصفحة. حدّث الصفحة قبل الحفظ.']);
            }
            $record->pdf_overrides = $values;
            if ($record->isDirty('pdf_overrides')) {
                $record->save();
                Log::create(['user_id' => auth()->id(), 'type' => 'cart', 'title' => 'تعديل PDF سلة '.$record->retailer_label.' #'.$record->id,
                    'log' => $values ? 'تم تعديل كميات وأسعار وحسابات PDF فقط.' : 'تمت استعادة بيانات العرض المحفوظة في PDF.']);
            }
        });
        try {
            $pdf->prepare($cart);
        } catch (CartQuoteException $e) {
            return redirect()->route('carts.show', $cart)->with('warning', 'تم حفظ تعديل PDF، لكن تعذر إنشاء الملف حالياً. أعد تحميل الصفحة للمحاولة.');
        }
        return redirect()->route('carts.show', $cart)->with('success', 'تم حفظ وإنشاء PDF. لم يتم إرسال رسالة للعميل.');
    }

    public function quotation(Request $request, CartQuote $cart, SheinQuotationPdf $pdf)
    {
        abort_unless($cart->status === 'succeeded', 409, 'Quotation is not ready');
        try {
            $cart = $pdf->prepare($cart);
        } catch (CartQuoteException $e) {
            return redirect()->route('carts.show', $cart)->with('error', $e->getMessage());
        }
        return response()->download(\Illuminate\Support\Facades\Storage::disk('local')->path($cart->pdf_path),
            $cart->retailer.'-quotation-'.$cart->id.'.pdf', ['Content-Type' => 'application/pdf'],
            $request->boolean('preview') ? 'inline' : 'attachment');
    }

    public function update(Request $request, CartQuote $cart)
    {
        $validated = $request->validate([
            'operator_status' => ['required', Rule::in(array_keys(CartQuote::OPERATOR_STATUSES))],
            'operator_notes' => ['nullable', 'string', 'max:3000'],
        ], [
            'operator_status.required' => 'يرجى اختيار حالة متابعة السلة.',
            'operator_status.in' => 'حالة متابعة السلة غير صحيحة.',
            'operator_notes.max' => 'يجب ألا تتجاوز ملاحظات المتابعة 3000 حرف.',
        ]);

        DB::transaction(function () use ($cart, $validated) {
            $record = CartQuote::lockForUpdate()->findOrFail($cart->id);
            $previous = $record->operator_status;
            $previousNotes = $record->operator_notes;
            $record->fill($validated);
            if (! $record->isDirty(['operator_status', 'operator_notes'])) {
                return;
            }
            if ($record->isDirty('operator_status')) {
                if ($record->operator_status === 'new') {
                    $record->customer_payment_method = null;
                    $record->customer_payment_updated_at = null;
                }
                $customerStatus = match ($record->operator_status) {
                    'new' => 'open',
                    'confirmed', 'buying', 'purchased' => 'confirmed',
                    'editing' => 'editing',
                    'cancelled', 'dismissed' => 'cancelled',
                    default => $record->customer_status,
                };
                if ($customerStatus !== $record->customer_status) {
                    $record->customer_status = $customerStatus;
                    $record->customer_updated_at = now();
                }
            }
            $record->handled_by = auth()->id();
            $record->operator_updated_at = now();
            app(CartStatusAuditService::class)->saveForOperator($record, auth()->user());

            Log::create([
                'user_id' => auth()->id(),
                'type' => 'cart',
                'title' => ($record->retailer === 'amazon' ? 'متابعة منتج أمازون #' : 'متابعة سلة '.$record->retailer_label.' #').$record->id,
                'log' => 'تم تعديل حالة المتابعة من ('.(CartQuote::OPERATOR_STATUSES[$previous] ?? $previous)
                    .') إلى ('.CartQuote::OPERATOR_STATUSES[$record->operator_status].').'
                    .($previousNotes !== $record->operator_notes ? ' تم تعديل ملاحظات المتابعة.' : ''),
            ]);
        });

        return redirect()->route('carts.show', $cart)->with('success', 'تم حفظ متابعة السلة بنجاح.');
    }

    private function notifications()
    {
        return Notif_orgs::where('user_id', auth()->id())->latest()->take(5)->get();
    }
}
